<?php
/**
 * File containing the expQueueContext class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * What a handler gets with its job: the lease (heartbeat), cooperative cancellation, progress, the result checkpoint
 * and the rate-limit hand-off of its resource.
 *
 *   public function run( expQueueJob $job, expQueueContext $context )
 *   {
 *       $answer = $context->checkpoint();             // a rerun after a crash: the stored answer, else null
 *       if ( $answer === null )
 *       {
 *           $answer = callTheService( $job->payloadArray(), $headers );
 *           $context->rateLimit( array( 'status' => 200, 'remaining_requests' => (int) $headers['x-remaining'] ) );
 *           $context->saveCheckpoint( $answer );      // saved before it is applied: never paid for twice
 *       }
 *       foreach ( $answer['items'] as $i => $item )
 *       {
 *           $context->throwIfCancelled();             // stops between steps when the job was cancelled
 *           applyItem( $item );
 *           $context->progress( $i + 1, count( $answer['items'] ) );   // also extends the lease
 *       }
 *       return array( 'items' => count( $answer['items'] ) );
 *   }
 */
class expQueueContext
{
    /** @var expQueueJob */
    protected $job;

    /** @var string the lease owner */
    protected $owner;

    /** @var expQueueWorker|null */
    protected $worker;

    /** @var int the last write of the lease */
    protected $lastBeat = 0;

    /** @var bool */
    protected $cancelled = false;

    /** @var bool whether the lease was lost (another worker has the job now) */
    protected $lost = false;

    public function __construct( expQueueJob $job, $owner, ?expQueueWorker $worker = null )
    {
        $this->job = $job;
        $this->owner = (string) $owner;
        $this->worker = $worker;
        $this->lastBeat = expQueue::now();
    }

    /** @return expQueueJob */
    public function job()
    {
        return $this->job;
    }

    /** @return array the job's payload */
    public function payload()
    {
        return $this->job->payloadArray();
    }

    /** @return int the number of this attempt (1 for the first run) */
    public function attempt()
    {
        return (int) $this->job->attribute( 'attempts' );
    }

    /** @return string the lease owner (worker id and claim number) */
    public function owner()
    {
        return $this->owner;
    }

    /** @return bool whether the lease was taken from this run (the reaper gave the job to another worker) */
    public function leaseLost()
    {
        return $this->lost;
    }

    /**
     * Extends the lease (timeout + LeaseMargin from now, or $seconds) and looks for a cancel request. Cheap enough to
     * call in a loop: it writes at most every 5 seconds unless $seconds is given.
     *
     * @param int|null $seconds the lease from now; null = the job's timeout + LeaseMargin
     * @return bool false when the job was cancelled or its lease is lost: stop as soon as possible
     */
    public function heartbeat( $seconds = null )
    {
        $now = expQueue::now();
        if ( $seconds === null && $now - $this->lastBeat < 5 )
            return !$this->cancelled && !$this->lost;
        $this->lastBeat = $now;
        $until = $now + ( $seconds !== null ? max( 1, (int) $seconds )
                                            : max( 1, (int) $this->job->attribute( 'timeout' ) ) + expQueueConfig::instance()->queueInt( 'LeaseMargin' ) );
        $db = expQueue::db();
        $id = (int) $this->job->attribute( 'id' );
        $db->query( "UPDATE expqueue_job SET lease_until = $until, modified = $now WHERE id = $id AND state = 'running' AND lease_owner = '"
                    . $db->escapeString( $this->owner ) . "'" );
        $rows = $db->arrayQuery( "SELECT state, lease_owner, cancel_requested FROM expqueue_job WHERE id = $id" );
        $row = is_array( $rows ) && isset( $rows[0] ) ? array_change_key_case( $rows[0], CASE_LOWER ) : array();
        if ( !$row || (string) $row['lease_owner'] !== $this->owner || $row['state'] !== expQueueJob::STATE_RUNNING )
            $this->lost = true;
        elseif ( (int) $row['cancel_requested'] )
            $this->cancelled = true;
        if ( $this->worker )
            $this->worker->beat();
        return !$this->cancelled && !$this->lost;
    }

    /** @return bool whether the job was cancelled while it runs (checks the database at most every 5 seconds) */
    public function isCancelled()
    {
        $this->heartbeat();
        return $this->cancelled || $this->lost;
    }

    /** Throws expQueueCancelledException when the job was cancelled (or its lease lost) meanwhile. */
    public function throwIfCancelled()
    {
        if ( $this->isCancelled() )
            throw new expQueueCancelledException( $this->lost ? 'the lease was lost to another worker' : 'cancelled while it ran' );
    }

    /**
     * Reports progress; extends the lease.
     *
     * @param int $done
     * @param int|null $total null keeps the total
     * @param string $message
     */
    public function progress( $done, $total = null, $message = '' )
    {
        $set = array( 'progress_done' => max( 0, (int) $done ) );
        if ( $total !== null )
            $set['progress_total'] = max( 0, (int) $total );
        if ( $message !== '' )
            $set['progress_message'] = expQueueJob::shorten( (string) $message, 255 );
        $parts = array();
        foreach ( $set as $field => $value )
            $parts[] = $field . ' = ' . expQueue::sqlValue( $value );
        $db = expQueue::db();
        $db->query( 'UPDATE expqueue_job SET ' . implode( ', ', $parts ) . ', modified = ' . expQueue::now() . ' WHERE id = '
                    . (int) $this->job->attribute( 'id' ) . " AND lease_owner = '" . $db->escapeString( $this->owner ) . "'" );
        foreach ( $set as $field => $value )
            $this->job->setAttribute( $field, $value );
        $this->heartbeat();
    }

    /** @return array|null the checkpoint stored by an earlier attempt (or this one), null when there is none */
    public function checkpoint()
    {
        $rows = expQueue::db()->arrayQuery( 'SELECT checkpoint FROM expqueue_job WHERE id = ' . (int) $this->job->attribute( 'id' ) );
        if ( !is_array( $rows ) || !isset( $rows[0] ) )
            return null;
        $row = array_change_key_case( $rows[0], CASE_LOWER );
        $value = (string) $row['checkpoint'];
        $data = $value !== '' ? json_decode( $value, true ) : null;
        return is_array( $data ) ? $data : null;
    }

    /**
     * Stores a checkpoint at once (its own short statement, no transaction): the outcome of work that costs money or
     * cannot be repeated, before it is applied. A later attempt reads it with checkpoint().
     *
     * @param array $data
     * @return bool
     */
    public function saveCheckpoint( array $data )
    {
        $json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
        $db = expQueue::db();
        $ok = $db->query( "UPDATE expqueue_job SET checkpoint = '" . $db->escapeString( (string) $json ) . "', modified = " . expQueue::now()
                          . ' WHERE id = ' . (int) $this->job->attribute( 'id' ) ) !== false;
        $this->job->setAttribute( 'checkpoint', (string) $json );
        if ( $ok )
            expQueueEvent::add( $this->job->attribute( 'id' ), $this->job->attribute( 'group_id' ), 'checkpoint', 'result checkpoint saved', array( 'bytes' => strlen( (string) $json ) ), $this->attempt(), $this->owner );
        return $ok;
    }

    /**
     * Hands the rate-limit information of an answer to the limiter of the job's resource (or another one).
     *
     * @param array $hints status, retry_after, remaining_requests, remaining_tokens, reset_at, reset_in
     * @param string|null $resource null = the job's resource
     */
    public function rateLimit( array $hints, $resource = null )
    {
        $key = $resource !== null ? (string) $resource : (string) $this->job->attribute( 'resource_key' );
        expQueueResource::observe( $key, $hints );
    }

    /** Records the actual cost of the job (in millionths of the currency unit). */
    public function cost( $micros )
    {
        expQueue::db()->query( 'UPDATE expqueue_job SET cost_actual_micros = ' . max( 0, (int) $micros ) . ' WHERE id = ' . (int) $this->job->attribute( 'id' ) );
        $this->job->setAttribute( 'cost_actual_micros', max( 0, (int) $micros ) );
    }

    /** Adds a line to the job's history (shown on the job page). */
    public function log( $message, array $data = array() )
    {
        expQueueEvent::add( $this->job->attribute( 'id' ), $this->job->attribute( 'group_id' ), 'log', $message, $data, $this->attempt(), $this->owner );
    }
}
