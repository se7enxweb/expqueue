<?php
/**
 * File containing the expQueueWorker class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * Runs jobs one after another until a limit is reached: the loop of ./console ext:expqueue:work, of the cron part
 * expqueue and of the systemd service. It only ever runs in a command line process: it refuses to start in a web
 * request (Apache/FPM) or inside a Velocity worker, so a job never holds up a page or leaks into a long-lived server
 * process. Commands that start it as root become the site user first (expQueueLock::dropPrivileges()).
 *
 * Per job: the scheduler leases it (expQueueScheduler::claim()), the worker becomes the user who queued it, calls the
 * handler outside any transaction, and ends the job with a compare-and-set on its lease (a run whose lease was reaped
 * meanwhile changes nothing). Between jobs the kernel's in-memory caches are cleared, so memory stays flat; the
 * worker stops at --max-jobs, --max-seconds, the memory limit, SIGTERM/SIGINT (after the current job) or, with
 * --until-empty, when nothing is ready.
 *
 * Its row in expqueue_worker carries the heartbeat the dashboard and the cron part read.
 */
class expQueueWorker
{
    /** @var array */
    protected $options;

    /** @var expQueueScheduler */
    protected $scheduler;

    /** @var callable|null receives a line of progress */
    public $output = null;

    /** @var string */
    protected $id;

    /** @var int */
    protected $claims = 0;

    /** @var int */
    protected $lastBeat = 0;

    /** @var bool set by SIGTERM/SIGINT */
    protected $stopRequested = false;

    /** @var array counters of this run */
    protected $stats = array( 'jobs' => 0, 'done' => 0, 'retry' => 0, 'dead' => 0, 'cancelled' => 0, 'other' => 0 );

    /** @var int|null the job being run */
    protected $current = null;

    /**
     * @param array $options classes (string[]), resources (string[]), max_jobs (0 = none), max_seconds (0 = none),
     *                       once (bool: at most one job), until_empty (bool: stop when nothing is ready),
     *                       origin (cli, cron, systemd), memory (bytes, 0 = from [WorkerSettings] MemoryLimit),
     *                       poll (seconds between empty rounds, from [WorkerSettings] PollInterval)
     */
    public function __construct( array $options = array() )
    {
        $config = expQueueConfig::instance();
        $this->options = $options + array(
            'classes' => array(),
            'resources' => array(),
            'max_jobs' => (int) $config->worker( 'MaxJobs', 500 ),
            'max_seconds' => (int) $config->worker( 'MaxSeconds', 3600 ),
            'once' => false,
            'until_empty' => false,
            'origin' => 'cli',
            'memory' => 0,
            'poll' => (float) $config->worker( 'PollInterval', 1 ),
        );
        if ( !$this->options['memory'] )
            $this->options['memory'] = expQueueConfig::bytes( $config->worker( 'MemoryLimit', '256M' ) );
        $this->scheduler = new expQueueScheduler( array( 'classes' => $this->options['classes'], 'resources' => $this->options['resources'] ) );
        $this->id = expQueueLock::me();
    }

    /** @return string|null why a worker cannot run in this process, null when it can */
    public static function refusal()
    {
        if ( PHP_SAPI !== 'cli' )
            return 'jobs run only in a command line process, never in a web request';
        if ( defined( 'QBIX_SERVER_VERSION' ) || class_exists( 'Q_WebServer', false ) )
            return 'jobs never run inside a Velocity worker; start ./console ext:expqueue:work instead';
        return null;
    }

    /** @return string this worker's id */
    public function id()
    {
        return $this->id;
    }

    /** @return expQueueScheduler */
    public function scheduler()
    {
        return $this->scheduler;
    }

    /**
     * The loop.
     *
     * @return array jobs, done, retry, dead, cancelled, other, seconds, stopped (why it stopped)
     */
    public function run()
    {
        $refusal = self::refusal();
        if ( $refusal !== null )
            throw new expQueueException( $refusal );
        $started = microtime( true );
        $this->register();
        $this->signals();
        $idle = 0.0;
        $why = 'limit';
        try
        {
            while ( true )
            {
                $this->dispatchSignals();
                if ( $this->stopRequested )
                {
                    $why = 'signal';
                    break;
                }
                if ( $this->options['max_jobs'] > 0 && $this->stats['jobs'] >= $this->options['max_jobs'] )
                {
                    $why = 'max-jobs';
                    break;
                }
                if ( $this->options['max_seconds'] > 0 && microtime( true ) - $started >= $this->options['max_seconds'] )
                {
                    $why = 'max-seconds';
                    break;
                }
                if ( $this->options['memory'] > 0 && memory_get_usage( true ) >= $this->options['memory'] )
                {
                    $why = 'memory';
                    break;
                }
                $this->scheduler->maintenance();
                $this->beat();
                $owner = $this->id . '#' . ( ++$this->claims );
                $job = $this->scheduler->claim( $owner );
                if ( !$job )
                {
                    if ( $this->options['once'] || $this->options['until_empty'] )
                    {
                        $why = 'empty';
                        break;
                    }
                    $idle = $idle > 0 ? min( (float) expQueueConfig::instance()->worker( 'IdlePollMax', 5 ), $idle * 1.5 ) : max( 0.05, $this->options['poll'] );
                    $this->sleep( $idle );
                    continue;
                }
                $idle = 0.0;
                $this->runJob( $job, $owner );
                if ( $this->options['once'] )
                {
                    $why = 'once';
                    break;
                }
            }
        }
        finally
        {
            $this->unregister();
        }
        return $this->stats + array( 'seconds' => round( microtime( true ) - $started, 2 ), 'stopped' => $why );
    }

    /**
     * Runs one leased job and ends it.
     *
     * @param expQueueJob $job
     * @param string $owner the lease owner
     * @return string the outcome (done, retry, dead, cancelled, defer, hold, lost)
     */
    public function runJob( expQueueJob $job, $owner )
    {
        $this->stats['jobs']++;
        $this->current = (int) $job->attribute( 'id' );
        $this->beat( true );
        $t0 = microtime( true );
        $context = new expQueueContext( $job, $owner, $this );
        $label = $job->attribute( 'type' ) . ' ' . $job->attribute( 'uuid' );
        try
        {
            $this->switchUser( $job );
            $handler = expQueue::handlerFor( $job->attribute( 'type' ) );
            $result = expQueueResult::from( $handler->run( $job, $context ) );
        }
        catch ( expQueueCancelledException $e )
        {
            $result = expQueueResult::cancelled( $e->getMessage() );
        }
        catch ( expQueuePermanentException $e )
        {
            $result = expQueueResult::fail( $e->getMessage(), $e->errorClass );
        }
        catch ( expQueueRetryableException $e )
        {
            $result = expQueueResult::retry( $e->getMessage(), $e->retryAfter, $e->errorClass );
        }
        catch ( Throwable $e )
        {
            $result = expQueueResult::retry( get_class( $e ) . ': ' . $e->getMessage(), 0, 'exception' );
        }
        $this->rollbackOpenTransactions();
        $ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
        $outcome = $this->finish( $job, $owner, $result, $ms );
        $this->stats[isset( $this->stats[$outcome] ) ? $outcome : 'other']++;
        $this->say( sprintf( '%s: %s in %d ms%s', $label, $outcome, $ms, $result->message !== '' ? ' (' . expQueueJob::shorten( $result->message, 100 ) . ')' : '' ) );
        $this->current = null;
        $this->clearCaches();
        return $outcome;
    }

    /**
     * Ends a run: the compare-and-set on the lease, the event, the resource's success, the dependants and the group.
     *
     * @return string the outcome
     */
    protected function finish( expQueueJob $job, $owner, expQueueResult $result, $ms )
    {
        $id = (int) $job->attribute( 'id' );
        $now = expQueue::now();
        $attempts = (int) $job->attribute( 'attempts' );
        $where = array( 'state' => expQueueJob::STATE_RUNNING, 'lease_owner' => (string) $owner );
        $outcome = $result->outcome;
        $data = array( 'ms' => $ms );
        switch ( $result->outcome )
        {
            case expQueueResult::DONE:
                $json = json_encode( $result->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
                $set = array( 'state' => expQueueJob::STATE_DONE, 'finished' => $now, 'lease_until' => 0, 'result' => (string) $json,
                              'error_class' => '', 'last_error' => '' );
                $kind = 'done';
                $message = $result->message !== '' ? $result->message : 'done';
                break;
            case expQueueResult::RETRY:
                if ( $attempts >= (int) $job->attribute( 'max_attempts' ) )
                {
                    $set = array( 'state' => expQueueJob::STATE_DEAD, 'finished' => $now, 'lease_until' => 0,
                                  'error_class' => substr( $result->errorClass, 0, 60 ), 'last_error' => expQueueJob::shorten( $result->message, 255 ) );
                    $kind = 'dead';
                    $outcome = 'dead';
                    $message = 'no attempt left: ' . $result->message;
                }
                else
                {
                    $delay = self::backoff( $job, $attempts, $result->seconds );
                    $set = array( 'state' => expQueueJob::STATE_RETRY, 'run_after' => $now + $delay, 'lease_until' => 0,
                                  'error_class' => substr( $result->errorClass, 0, 60 ), 'last_error' => expQueueJob::shorten( $result->message, 255 ) );
                    $kind = 'retry';
                    $message = "retry in {$delay} s: " . $result->message;
                    $data['delay'] = $delay;
                    $data['retry_after'] = $result->seconds;
                }
                break;
            case expQueueResult::FAIL:
                $set = array( 'state' => expQueueJob::STATE_DEAD, 'finished' => $now, 'lease_until' => 0,
                              'error_class' => substr( $result->errorClass, 0, 60 ), 'last_error' => expQueueJob::shorten( $result->message, 255 ) );
                $kind = 'dead';
                $outcome = 'dead';
                $message = $result->message;
                break;
            case expQueueResult::DEFER:
                $set = array( 'state' => expQueueJob::STATE_RETRY, 'run_after' => $now + max( 1, $result->seconds ), 'lease_until' => 0,
                              'attempts' => max( 0, $attempts - 1 ) );
                $kind = 'defer';
                $message = 'deferred ' . max( 1, $result->seconds ) . ' s' . ( $result->message !== '' ? ': ' . $result->message : '' );
                break;
            case expQueueResult::HOLD:
                $set = array( 'state' => expQueueJob::STATE_HELD, 'lease_until' => 0, 'attempts' => max( 0, $attempts - 1 ),
                              'last_error' => expQueueJob::shorten( $result->message, 255 ) );
                $kind = 'hold';
                $message = 'held' . ( $result->message !== '' ? ': ' . $result->message : '' );
                break;
            default:
                $set = array( 'state' => expQueueJob::STATE_CANCELLED, 'finished' => $now, 'lease_until' => 0,
                              'last_error' => expQueueJob::shorten( $result->message !== '' ? $result->message : 'cancelled', 255 ) );
                $kind = 'cancel';
                $outcome = 'cancelled';
                $message = $result->message !== '' ? $result->message : 'cancelled while it ran';
        }
        if ( !expQueue::casUpdate( $id, $where, $set ) )
        {
            // the lease was reaped meanwhile: another worker owns the job now, this run changes nothing
            expQueueEvent::add( $id, $job->attribute( 'group_id' ), 'lost', 'the run ended after its lease was taken: its outcome (' . $outcome . ') is ignored',
                                $data, $attempts, (string) $owner );
            return 'lost';
        }
        expQueueEvent::add( $id, $job->attribute( 'group_id' ), $kind, $message, $data, $attempts, (string) $owner );
        $resource = (string) $job->attribute( 'resource_key' );
        if ( $kind === 'done' )
            expQueueResource::success( $resource );
        if ( in_array( $kind, array( 'done', 'dead', 'cancel' ), true ) )
            expQueue::afterFinal( $job );
        if ( $kind === 'dead' )
            expQueue::audit( 'job.dead', array( 'job' => $job->attribute( 'uuid' ), 'type' => $job->attribute( 'type' ),
                                                'error' => $set['error_class'], 'attempts' => $attempts ) );
        return $outcome;
    }

    /**
     * The wait before the next attempt: exponential with jitter, never below the retry-after the handler reported.
     *
     *   max( retry_after, min( MaxBackoff, RetryBase * 2^(attempt-1) ) * random(0.5 .. 1.0) )
     *
     * @return int seconds
     */
    public static function backoff( expQueueJob $job, $attempt, $retryAfter = 0 )
    {
        $config = expQueueConfig::instance();
        $type = $config->jobType( $job->attribute( 'type' ) );
        $base = $type && $type['retry_base'] > 0 ? $type['retry_base'] : $config->queueInt( 'RetryBase', 1 );
        $max = $type && $type['max_backoff'] > 0 ? $type['max_backoff'] : $config->queueInt( 'MaxBackoff', 1 );
        $delay = min( $max, $base * pow( 2, max( 0, (int) $attempt - 1 ) ) );
        $jittered = (int) ceil( $delay * mt_rand( 500, 1000 ) / 1000 );
        return max( (int) $retryAfter, max( 1, $jittered ) );
    }

    // ---- the worker's own row ---------------------------------------------------------------------------------------

    protected function register()
    {
        $db = expQueue::db();
        $now = expQueue::now();
        $filter = trim( ( $this->options['classes'] ? 'class=' . implode( ',', $this->options['classes'] ) : '' ) . ' '
                        . ( $this->options['resources'] ? 'resource=' . implode( ',', $this->options['resources'] ) : '' ) );
        $values = array( 'worker_id' => $this->id, 'host' => substr( php_uname( 'n' ), 0, 60 ), 'pid' => getmypid(),
                         'os_user' => substr( expQueueLock::processUser(), 0, 40 ), 'origin' => substr( (string) $this->options['origin'], 0, 16 ),
                         'started' => $now, 'heartbeat' => $now, 'jobs_done' => 0, 'jobs_failed' => 0, 'current_job' => 0,
                         'state' => 'running', 'filter' => substr( $filter, 0, 255 ), 'modified' => $now );
        // a second run in the same process (the cron part, tests) has the same id: its row is replaced
        $db->query( "DELETE FROM expqueue_worker WHERE worker_id = '" . $db->escapeString( $this->id ) . "'" );
        $db->query( 'INSERT INTO expqueue_worker ( ' . implode( ', ', array_keys( $values ) ) . ' ) VALUES ( '
                    . implode( ', ', array_map( array( 'expQueue', 'sqlValue' ), $values ) ) . ' )' );
        $this->lastBeat = $now;
    }

    /** Writes the worker's heartbeat (at most every WorkerHeartbeat seconds unless forced). */
    public function beat( $force = false )
    {
        $now = expQueue::now();
        if ( !$force && $now - $this->lastBeat < (int) expQueueConfig::instance()->worker( 'Heartbeat', 10 ) )
            return;
        $this->lastBeat = $now;
        $db = expQueue::db();
        $db->query( 'UPDATE expqueue_worker SET heartbeat = ' . $now . ', current_job = ' . (int) $this->current
                    . ', jobs_done = ' . (int) $this->stats['done'] . ', jobs_failed = ' . (int) ( $this->stats['dead'] + $this->stats['retry'] )
                    . ", state = 'running', modified = $now WHERE worker_id = '" . $db->escapeString( $this->id ) . "'" );
    }

    protected function unregister()
    {
        $db = expQueue::db();
        $now = expQueue::now();
        $db->query( "UPDATE expqueue_worker SET state = 'stopped', current_job = 0, heartbeat = $now, jobs_done = " . (int) $this->stats['done']
                    . ', jobs_failed = ' . (int) ( $this->stats['dead'] + $this->stats['retry'] )
                    . ", modified = $now WHERE worker_id = '" . $db->escapeString( $this->id ) . "'" );
    }

    /**
     * The workers that run now (a heartbeat within [WorkerSettings] StaleSeconds).
     *
     * @param string|null $origin only of this origin (cli, cron, systemd)
     * @return array[] rows
     */
    public static function live( $origin = null )
    {
        $since = expQueue::now() - max( 30, (int) expQueueConfig::instance()->worker( 'StaleSeconds', 90 ) );
        $where = "state = 'running' AND heartbeat >= $since";
        if ( $origin !== null )
            $where .= " AND origin = '" . expQueue::db()->escapeString( $origin ) . "'";
        $rows = expQueue::db()->arrayQuery( "SELECT * FROM expqueue_worker WHERE $where ORDER BY started" );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
            $out[] = array_change_key_case( $row, CASE_LOWER );
        return $out;
    }

    /** @return array[] the workers seen in the last day, newest first */
    public static function recent( $limit = 20 )
    {
        $rows = expQueue::db()->arrayQuery( 'SELECT * FROM expqueue_worker ORDER BY heartbeat DESC', array( 'limit' => (int) $limit ) );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
            $out[] = array_change_key_case( $row, CASE_LOWER );
        return $out;
    }

    // ---- what tests replace -------------------------------------------------------------------------------------

    /** Runs the job as the user who queued it (every language counts, as in the cronjobs). */
    protected function switchUser( expQueueJob $job )
    {
        $userID = (int) $job->attribute( 'user_id' );
        if ( !$userID || !class_exists( 'eZUser' ) )
            return;
        $user = eZUser::fetch( $userID );
        if ( !$user instanceof eZUser )
            throw new expQueuePermanentException( "the user $userID who queued the job does not exist any more", 'no_user' );
        if ( (int) eZUser::currentUserID() !== $userID )
            eZUser::setCurrentlyLoggedInUser( $user, $userID, eZUser::NO_SESSION_REGENERATE );
        if ( class_exists( 'eZContentLanguage' ) )
            eZContentLanguage::setCronjobMode( true );
    }

    /** A handler that left a transaction open: back to the outermost level, so the next job starts clean. */
    protected function rollbackOpenTransactions()
    {
        $db = expQueue::db();
        if ( !method_exists( $db, 'transactionCounter' ) )
            return;
        $guard = 0;
        while ( $db->transactionCounter() > 0 && $guard++ < 100 )
            $db->rollback();
    }

    /** Clears the kernel's in-memory caches between jobs, so memory stays flat. */
    protected function clearCaches()
    {
        if ( class_exists( 'eZContentObject', false ) )
            eZContentObject::clearCache();
        unset( $GLOBALS['eZContentClassObjectCache'], $GLOBALS['eZContentObjectTreeNodeCache'] );
        if ( class_exists( 'eZDebug', false ) )
            eZDebug::instance()->reset();
        gc_collect_cycles();
    }

    protected function signals()
    {
        if ( !function_exists( 'pcntl_signal' ) )
            return;
        $stop = function () { $this->stopRequested = true; };
        pcntl_signal( SIGTERM, $stop );
        pcntl_signal( SIGINT, $stop );
    }

    protected function dispatchSignals()
    {
        if ( function_exists( 'pcntl_signal_dispatch' ) )
            pcntl_signal_dispatch();
    }

    /** Sleeps, waking up for signals. */
    protected function sleep( $seconds )
    {
        $until = microtime( true ) + $seconds;
        while ( microtime( true ) < $until )
        {
            usleep( (int) min( 200000, max( 1000, ( $until - microtime( true ) ) * 1000000 ) ) );
            $this->dispatchSignals();
            if ( $this->stopRequested )
                return;
        }
    }

    protected function say( $line )
    {
        if ( $this->output )
            call_user_func( $this->output, $line );
    }
}
