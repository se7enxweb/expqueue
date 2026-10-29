<?php
/**
 * File containing the expQueueJob class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * One job (table expqueue_job). Producers get it from expQueue::enqueue() and read it back with expQueue::fetch();
 * only expQueue, expQueueScheduler and expQueueWorker change its state, always with a compare-and-set update and an
 * event row (expqueue_event).
 *
 * States:
 *   queued     waiting for its parent job or for its not-before time
 *   ready      can run now
 *   running    leased by a worker (lease_owner, lease_until)
 *   retry      failed, waits for its backoff (run_after), then ready again
 *   held       stopped by its handler (e.g. a budget is used up) or by an operator, until it is resumed
 *   done       finished with a result
 *   dead       the dead letter: max_attempts used up, or an error another attempt cannot fix
 *   cancelled  cancelled before or while it ran
 */
class expQueueJob extends eZPersistentObject
{
    const STATE_QUEUED = 'queued';
    const STATE_READY = 'ready';
    const STATE_RUNNING = 'running';
    const STATE_RETRY = 'retry';
    const STATE_HELD = 'held';
    const STATE_DONE = 'done';
    const STATE_DEAD = 'dead';
    const STATE_CANCELLED = 'cancelled';

    /** @var bool set by expQueue::enqueue() when an existing job with the same idempotency key was returned */
    public $duplicate = false;

    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'uuid' => $str( 'UUID' ),
                'type' => $str( 'Type' ),
                'class' => $str( 'Class', 'bulk' ),
                'priority' => array( 'name' => 'Priority', 'datatype' => 'integer', 'default' => 50, 'required' => true ),
                'state' => $str( 'State', self::STATE_READY ),
                'group_id' => $int( 'GroupID' ),
                'parent_id' => $int( 'ParentID' ),
                'resource_key' => $str( 'ResourceKey' ),
                'lane' => $str( 'Lane' ),
                'site' => $str( 'Site' ),
                'user_id' => $int( 'UserID' ),
                'idempotency_key' => $str( 'IdempotencyKey' ),
                'attempts' => $int( 'Attempts' ),
                'max_attempts' => $int( 'MaxAttempts' ),
                'timeout' => $int( 'Timeout' ),
                'run_after' => $int( 'RunAfter' ),
                'ready_since' => $int( 'ReadySince' ),
                'deadline' => $int( 'Deadline' ),
                'lease_owner' => $str( 'LeaseOwner' ),
                'lease_until' => $int( 'LeaseUntil' ),
                'cancel_requested' => $int( 'CancelRequested' ),
                'external_ref' => $str( 'ExternalRef' ),
                'progress_done' => $int( 'ProgressDone' ),
                'progress_total' => $int( 'ProgressTotal' ),
                'progress_message' => $str( 'ProgressMessage' ),
                'cost_estimate_micros' => $int( 'CostEstimateMicros' ),
                'cost_reserved_micros' => $int( 'CostReservedMicros' ),
                'cost_actual_micros' => $int( 'CostActualMicros' ),
                'payload' => $str( 'Payload' ),
                'checkpoint' => $str( 'Checkpoint' ),
                'result' => $str( 'Result' ),
                'error_class' => $str( 'ErrorClass' ),
                'last_error' => $str( 'LastError' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
                'started' => $int( 'Started' ),
                'finished' => $int( 'Finished' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array(
                'payload_array' => 'payloadArray',
                'result_array' => 'resultArray',
                'checkpoint_array' => 'checkpointArray',
                'payload_summary' => 'payloadSummary',
                'summary' => 'summary',
                'is_final' => 'isFinal',
                'is_active' => 'isActive',
                'wait_seconds' => 'waitSeconds',
                'run_seconds' => 'runSeconds',
                'progress_percent' => 'progressPercent',
                'group' => 'group',
                'parent' => 'parent',
                'can_cancel' => 'canCancel',
                'can_retry' => 'canRetry',
                'lease_left' => 'leaseLeft',
            ),
            'class_name' => 'expQueueJob',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expqueue_job',
        );
    }

    /** @return string[] the states a job can end in */
    public static function finalStates()
    {
        return array( self::STATE_DONE, self::STATE_DEAD, self::STATE_CANCELLED );
    }

    /** @return string[] the states of a job that has not ended */
    public static function activeStates()
    {
        return array( self::STATE_QUEUED, self::STATE_READY, self::STATE_RUNNING, self::STATE_RETRY, self::STATE_HELD );
    }

    /** @return string[] every state, in the order the admin pages show them */
    public static function states()
    {
        return array_merge( self::activeStates(), self::finalStates() );
    }

    /** @return expQueueJob|null */
    public static function fetchByID( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int) $id ), true );
    }

    /** @return expQueueJob|null */
    public static function fetchByUUID( $uuid )
    {
        if ( !preg_match( '/^[0-9a-f-]{36}$/', (string) $uuid ) )
            return null;
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'uuid' => (string) $uuid ), true );
    }

    /** @return expQueueJob a fresh copy from the database (null when it is gone) */
    public function reload()
    {
        return self::fetchByID( $this->attribute( 'id' ) );
    }

    /** @return array */
    public function payloadArray()
    {
        return self::decode( $this->attribute( 'payload' ) );
    }

    /** @return array */
    public function resultArray()
    {
        return self::decode( $this->attribute( 'result' ) );
    }

    /** @return array|null the stored checkpoint, null when there is none */
    public function checkpointArray()
    {
        $value = (string) $this->attribute( 'checkpoint' );
        if ( $value === '' )
            return null;
        $data = json_decode( $value, true );
        return is_array( $data ) ? $data : null;
    }

    /** @return array a JSON column as an array (empty when it is empty or not JSON) */
    public static function decode( $value )
    {
        $value = (string) $value;
        if ( $value === '' )
            return array();
        $data = json_decode( $value, true );
        return is_array( $data ) ? $data : array();
    }

    /**
     * The payload for display: keys whose name looks like a secret are masked, long values shortened, nested values
     * summarised. Secrets are not meant to be in a payload at all; this is the second line of defence.
     *
     * @return array key => string
     */
    public function payloadSummary()
    {
        return self::summarise( $this->payloadArray() );
    }

    /** @return array key => string, the masked and shortened form of a payload or result */
    public static function summarise( array $data, $depth = 0 )
    {
        $out = array();
        $i = 0;
        foreach ( $data as $key => $value )
        {
            if ( ++$i > 40 )
            {
                $out['…'] = ( count( $data ) - 40 ) . ' more';
                break;
            }
            if ( preg_match( '/pass(word)?|secret|token|api[_-]?key|credential|authori[sz]ation|cookie|private/i', (string) $key ) )
                $out[$key] = '********';
            elseif ( is_array( $value ) )
                $out[$key] = $depth < 1 && count( $value ) <= 6 && array_filter( $value, 'is_scalar' ) === $value
                           ? self::shorten( json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
                           : 'array(' . count( $value ) . ')';
            elseif ( is_bool( $value ) )
                $out[$key] = $value ? 'true' : 'false';
            elseif ( $value === null )
                $out[$key] = 'null';
            else
                $out[$key] = self::shorten( (string) $value );
        }
        return $out;
    }

    /** @return string at most $length characters */
    public static function shorten( $text, $length = 120 )
    {
        $text = preg_replace( '/\s+/u', ' ', (string) $text );
        return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length - 1 ) . '…' : $text;
    }

    /** @return string the one-line summary of the handler (describe()), else the type */
    public function summary()
    {
        $handler = expQueue::handlerFor( $this->attribute( 'type' ), false );
        if ( $handler instanceof expQueueHandlerBase )
        {
            try
            {
                $text = (string) $handler->describe( $this );
                if ( $text !== '' )
                    return self::shorten( $text, 160 );
            }
            catch ( Throwable $e )
            {
            }
        }
        return (string) $this->attribute( 'type' );
    }

    /** @return bool */
    public function isFinal()
    {
        return in_array( $this->attribute( 'state' ), self::finalStates(), true );
    }

    /** @return bool */
    public function isActive()
    {
        return !$this->isFinal();
    }

    /** @return int seconds from queued (or ready) to start, or until now while it waits */
    public function waitSeconds()
    {
        $from = (int) $this->attribute( 'created' );
        $to = (int) $this->attribute( 'started' ) ?: ( $this->isFinal() ? (int) $this->attribute( 'finished' ) : time() );
        return max( 0, $to - $from );
    }

    /** @return int seconds of the last run (until now while it runs) */
    public function runSeconds()
    {
        $start = (int) $this->attribute( 'started' );
        if ( !$start )
            return 0;
        $end = $this->attribute( 'state' ) === self::STATE_RUNNING ? time() : (int) $this->attribute( 'finished' );
        return max( 0, $end - $start );
    }

    /** @return int 0..100, or -1 when the handler reports no total */
    public function progressPercent()
    {
        $total = (int) $this->attribute( 'progress_total' );
        if ( $total <= 0 )
            return $this->attribute( 'state' ) === self::STATE_DONE ? 100 : -1;
        return (int) min( 100, floor( 100 * (int) $this->attribute( 'progress_done' ) / $total ) );
    }

    /** @return int seconds left on the lease of a running job */
    public function leaseLeft()
    {
        return $this->attribute( 'state' ) === self::STATE_RUNNING ? (int) $this->attribute( 'lease_until' ) - time() : 0;
    }

    /** @return expQueueGroup|null */
    public function group()
    {
        $id = (int) $this->attribute( 'group_id' );
        return $id ? expQueueGroup::fetchByID( $id ) : null;
    }

    /** @return expQueueJob|null */
    public function parent()
    {
        $id = (int) $this->attribute( 'parent_id' );
        return $id ? self::fetchByID( $id ) : null;
    }

    /** @return bool */
    public function canCancel()
    {
        return $this->isActive() && !(int) $this->attribute( 'cancel_requested' );
    }

    /** @return bool */
    public function canRetry()
    {
        return in_array( $this->attribute( 'state' ), array( self::STATE_DEAD, self::STATE_CANCELLED, self::STATE_HELD, self::STATE_RETRY ), true );
    }

    /** @return array the job as an array (the CLI's --json, the MCP tools) */
    public function toArray( $withData = true )
    {
        $out = array();
        foreach ( array_keys( self::definition()['fields'] ) as $field )
        {
            if ( in_array( $field, array( 'payload', 'checkpoint', 'result' ), true ) )
                continue;
            $out[$field] = $this->attribute( $field );
        }
        if ( $withData )
        {
            $out['payload'] = $this->payloadSummary();
            $out['result'] = $this->resultArray();
            $out['has_checkpoint'] = $this->checkpointArray() !== null;
        }
        return $out;
    }
}
