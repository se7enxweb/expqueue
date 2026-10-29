<?php
/**
 * File containing the expQueueCommand class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The commands ./console ext:expqueue:<name> (extension/expqueue/bin/php/<name>.php); each file only calls
 * expQueueCommand::main( '<name>' ). Started as root, every command but service becomes the site user before it reads
 * a setting or opens the database, so nothing it writes (SQLite journals, lock files) belongs to root.
 *
 * Exit codes: 0 success, 1 failure or a bad argument, 2 nothing to do (e.g. the job had ended), 3 unknown job.
 */
class expQueueCommand
{
    /** @var eZCLI */
    protected $cli;

    /** @var eZScript */
    protected $script;

    /** @var array */
    protected $options = array();

    /** @var string[] */
    protected $args = array();

    /** The option specs and help of each command */
    const COMMANDS = array(
        'work' => array( 'Runs queued jobs until a limit is reached (the worker).',
                         '[once][until-empty][max-jobs:][max-seconds:][class:][resource:][origin:][memory:]',
                         array( 'once' => 'run at most one job, then leave',
                                'until-empty' => 'leave as soon as no job is ready',
                                'max-jobs' => 'leave after this many jobs (default [WorkerSettings] MaxJobs; 0 = no limit)',
                                'max-seconds' => 'leave after this many seconds (default [WorkerSettings] MaxSeconds; 0 = no limit)',
                                'class' => 'only these priority classes (comma separated)',
                                'resource' => 'only jobs of these resources (comma separated)',
                                'origin' => 'cli, cron or systemd (shown on the dashboard)',
                                'memory' => 'leave when the memory use reaches this (e.g. 256M)' ) ),
        'status' => array( 'The queue at a glance: states, classes, resources, workers, cron and the service.', '[json]',
                           array( 'json' => 'print the report as JSON' ) ),
        'list' => array( 'Lists jobs, newest first.', '[state:][class:][type:][resource:][group:][limit:][offset:][json]',
                         array( 'state' => 'only these states (comma separated)', 'class' => 'only these classes', 'type' => 'only these types',
                                'resource' => 'only these resources', 'group' => 'only the jobs of this group (uuid or id)',
                                'limit' => 'at most this many (default 50)', 'offset' => 'skip this many', 'json' => 'print JSON' ) ),
        'show' => array( 'Shows one job with its history: show <uuid|id>.', '[json][events:]',
                         array( 'json' => 'print JSON', 'events' => 'the last this many events (default 50)' ) ),
        'enqueue' => array( 'Queues a job: enqueue <type> (the built-in test types are expqueue.noop, expqueue.sleep, expqueue.fail).',
                            '[payload:][class:][priority:][group:][new-group:][idempotency-key:][resource:][not-before:][max-attempts:][timeout:][depends-on:][count:][json]',
                            array( 'payload' => 'the payload as JSON, e.g. {"seconds":2}', 'class' => 'the priority class', 'priority' => '0..100',
                                   'group' => 'an existing group (uuid or id)', 'new-group' => 'create a group with this label and queue into it',
                                   'idempotency-key' => 'a key unique per type', 'resource' => 'the concurrency key',
                                   'not-before' => 'seconds from now, or a timestamp', 'max-attempts' => 'attempts before the dead letter',
                                   'timeout' => 'seconds per run', 'depends-on' => 'a job (uuid or id) that must be done first',
                                   'count' => 'queue this many copies (for tests)', 'json' => 'print JSON' ) ),
        'cancel' => array( 'Cancels jobs: cancel <uuid|id> [...] or --group=<uuid|id>.', '[group:][reason:]',
                           array( 'group' => 'cancel every job of this group', 'reason' => 'kept in the job history' ) ),
        'pause' => array( 'Pauses the queue, a class, a resource or a group: pause queue | pause class <name> | pause resource <key> | pause group <uuid>.', '',
                          array() ),
        'resume' => array( 'Resumes what pause paused: resume queue | resume class <name> | resume resource <key> | resume group <uuid>.', '',
                           array() ),
        'retry' => array( 'Requeues dead, cancelled or held jobs with their attempts reset: retry <uuid|id> [...] or --dead [--type=...].', '[dead][type:]',
                          array( 'dead' => 'requeue every dead job', 'type' => 'with --dead: only this type' ) ),
        'purge' => array( 'Removes old done, cancelled and dead jobs with their events.', '[days:][dead-days:][type:][dry-run]',
                          array( 'days' => 'done and cancelled jobs older than this (default KeepDays)', 'dead-days' => 'dead jobs older than this (default KeepDeadDays)',
                                 'type' => 'only jobs of this type', 'dry-run' => 'only count' ) ),
        'install-schema' => array( 'Creates or upgrades the tables of expqueue (idempotent) and reports what is missing.', '[install][check][print:][write-files]',
                                   array( 'install' => 'create the missing tables, columns and indexes (the default)', 'check' => 'only report what is missing',
                                          'print' => 'print the SQL of mysql, postgresql, sqlite or oracle', 'write-files' => 'rewrite share/db_schema.dba and sql/*/schema*' ) ),
        'service' => array( 'The systemd service of the workers: service install|start|stop|status|uninstall (root, except status and --dry-run).', '[dry-run][instances:]',
                            array( 'dry-run' => 'show what it would write and run', 'instances' => 'worker processes (default [ServiceSettings] Instances)' ) ),
    );

    /**
     * The entry point of bin/php/<name>.php.
     *
     * @param string $name
     */
    public static function main( $name )
    {
        $root = dirname( __DIR__, 3 );
        if ( $name !== 'service' )
        {
            $error = expQueueLock::dropPrivileges( $root );
            if ( $error !== null )
            {
                fwrite( STDERR, "FAIL $error\n" );
                exit( 1 );
            }
        }
        elseif ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 && !in_array( '--allow-root-user', $_SERVER['argv'], true ) )
        {
            // the service needs root by design; the kernel's guard against root scripts does not apply to it
            $_SERVER['argv'][] = '--allow-root-user';
            $GLOBALS['argv'] = $_SERVER['argv'];
        }
        $command = new self();
        exit( $command->start( $name ) );
    }

    /** @return int exit code */
    public function start( $name )
    {
        if ( !isset( self::COMMANDS[$name] ) )
        {
            fwrite( STDERR, "FAIL unknown command $name\n" );
            return 1;
        }
        list( $description, $spec, $help ) = self::COMMANDS[$name];
        $this->cli = eZCLI::instance();
        $this->script = eZScript::instance( array( 'description' => "expqueue: $description\n", 'use-session' => false,
                                                   'use-modules' => true, 'use-extensions' => true ) );
        $this->script->startup();
        $this->options = $this->script->getOptions( $spec, '', $help );
        $this->script->initialize();
        $this->args = isset( $this->options['arguments'] ) ? array_values( $this->options['arguments'] ) : array();
        if ( $name !== 'install-schema' && $name !== 'service' && !expQueue::installed() )
        {
            $this->cli->error( 'FAIL the tables of expqueue are missing in this database: run ./console ext:expqueue:install-schema' );
            $this->script->shutdown( 1 );
            return 1;
        }
        $method = 'cmd' . str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) );
        try
        {
            $code = (int) $this->$method();
        }
        catch ( expQueueException $e )
        {
            $this->cli->error( 'FAIL ' . $e->getMessage() );
            $code = 1;
        }
        $this->script->shutdown( $code );
        return $code;
    }

    // ---- commands ---------------------------------------------------------------------------------------------------

    protected function cmdWork()
    {
        $refusal = expQueueWorker::refusal();
        if ( $refusal !== null )
        {
            $this->cli->error( "FAIL $refusal" );
            return 1;
        }
        $o = $this->options;
        $options = array( 'classes' => $this->listOption( 'class' ), 'resources' => $this->listOption( 'resource' ),
                          'once' => !empty( $o['once'] ), 'until_empty' => !empty( $o['until-empty'] ),
                          'origin' => !empty( $o['origin'] ) ? preg_replace( '/[^a-z]/', '', (string) $o['origin'] ) : 'cli' );
        foreach ( array( 'max-jobs' => 'max_jobs', 'max-seconds' => 'max_seconds' ) as $opt => $key )
            if ( isset( $o[$opt] ) && $o[$opt] !== false && $o[$opt] !== null )
                $options[$key] = max( 0, (int) $o[$opt] );
        if ( !empty( $o['memory'] ) )
            $options['memory'] = expQueueConfig::bytes( $o['memory'] );
        foreach ( $options['classes'] as $class )
            if ( !expQueueConfig::instance()->hasClass( $class ) )
            {
                $this->cli->error( "FAIL unknown class $class" );
                return 1;
            }
        $worker = new expQueueWorker( $options );
        $cli = $this->cli;
        $worker->output = function ( $line ) use ( $cli ) { $cli->output( date( 'H:i:s ' ) . $line ); };
        $this->cli->output( 'worker ' . $worker->id() . ' as ' . expQueueLock::processUser() . ' (' . $options['origin'] . ')' );
        $r = $worker->run();
        $this->cli->output( sprintf( 'PASS worker left (%s) after %d job(s) in %.1f s: %d done, %d retry, %d dead, %d cancelled, %d other',
                                     $r['stopped'], $r['jobs'], $r['seconds'], $r['done'], $r['retry'], $r['dead'], $r['cancelled'], $r['other'] ) );
        return 0;
    }

    protected function cmdStatus()
    {
        $r = expQueueStats::report();
        if ( !empty( $this->options['json'] ) )
        {
            $r['dead_recent'] = array_map( function ( $j ) { return $j->toArray( false ); }, $r['dead_recent'] );
            $r['running_jobs'] = array_map( function ( $j ) { return $j->toArray( false ); }, $r['running_jobs'] );
            $r['groups'] = array_map( function ( $g ) { return $g->attribute( 'uuid' ); }, $r['groups'] );
            $this->cli->output( json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
            return 0;
        }
        $s = $r['states'];
        $this->cli->output( 'queue: ' . ( $r['paused'] ? 'PAUSED' : 'running' ) . ', ' . $r['totals']['active'] . ' active of ' . $r['totals']['all'] . ' jobs' );
        $this->cli->output( '  ' . implode( '  ', array_map( function ( $k ) use ( $s ) { return "$k " . $s[$k]; }, array_keys( $s ) ) ) );
        $this->cli->output( '' );
        $rows = array( array( 'class', 'weight', 'ready', 'running', 'max', 'waiting', 'dead', 'oldest', 'p95 wait 1h', 'done 1h', 'paused' ) );
        foreach ( $r['classes'] as $c )
            $rows[] = array( $c['name'], (string) $c['weight'] . ( $c['reserved'] ? ' (reserved)' : '' ), $c['ready'], $c['running'], $c['max_running'] ?: '-',
                             $c['waiting'], $c['dead'], $c['ready'] ? self::duration( $c['oldest_wait'] ) : '-', $c['wait_p95'] === null ? '-' : self::duration( $c['wait_p95'] ),
                             $c['done_hour'], $c['paused'] ? 'yes' : '' );
        $this->table( $rows );
        if ( $r['resources'] )
        {
            $this->cli->output( '' );
            $rows = array( array( 'resource', 'running', 'limit', 'max', 'ready', 'cooldown', 'remaining', 'status', 'paused' ) );
            foreach ( $r['resources'] as $res )
                $rows[] = array( $res['key'], $res['running'], $res['limit'], $res['max'], $res['ready'], $res['cooling'] ? $res['cooling'] . ' s' : '',
                                 $res['remaining_requests'] >= 0 ? $res['remaining_requests'] : '', $res['last_status'], $res['paused'] ? 'yes' : '' );
            $this->table( $rows );
        }
        $this->cli->output( '' );
        $this->cli->output( 'workers alive: ' . count( $r['workers'] ) );
        foreach ( $r['workers'] as $w )
            $this->cli->output( sprintf( '  %s  %s as %s, up %s, heartbeat %s ago, %d done%s', $w['worker_id'], $w['origin'], $w['os_user'],
                                         self::duration( $r['generated'] - (int) $w['started'] ), self::duration( $r['generated'] - (int) $w['heartbeat'] ),
                                         (int) $w['jobs_done'], (int) $w['current_job'] ? ', running job ' . (int) $w['current_job'] : '' ) );
        $cron = $r['cron'];
        $this->cli->output( 'cron part expqueue: ' . ( $cron['last'] ? 'last run ' . self::duration( $cron['ago'] ) . ' ago' . ( $cron['ok'] ? '' : ' (late: is it in the crontab?)' ) : 'never ran' ) );
        $svc = $r['service'];
        $this->cli->output( 'systemd service ' . $svc['unit'] . ': ' . ( $svc['installed'] ? 'installed, ' . $svc['workers'] . ' worker(s) alive' : 'not installed (cron is the worker)' ) );
        return 0;
    }

    protected function cmdList()
    {
        $filter = array();
        foreach ( array( 'state' => 'state', 'class' => 'class', 'type' => 'type', 'resource' => 'resource_key' ) as $opt => $key )
            if ( $this->listOption( $opt ) )
                $filter[$key] = $this->listOption( $opt );
        if ( !empty( $this->options['group'] ) )
        {
            $group = expQueue::resolveGroup( $this->options['group'] );
            if ( !$group )
            {
                $this->cli->error( 'FAIL unknown group ' . $this->options['group'] );
                return 3;
            }
            $filter['group_id'] = (int) $group->attribute( 'id' );
        }
        $limit = !empty( $this->options['limit'] ) ? (int) $this->options['limit'] : 50;
        $offset = !empty( $this->options['offset'] ) ? (int) $this->options['offset'] : 0;
        $jobs = expQueue::listJobs( $filter, $offset, $limit );
        if ( !empty( $this->options['json'] ) )
        {
            $this->cli->output( json_encode( array_map( function ( $j ) { return $j->toArray( false ); }, $jobs ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            return 0;
        }
        $rows = array( array( 'id', 'uuid', 'type', 'class', 'prio', 'state', 'att', 'resource', 'age', 'last error' ) );
        foreach ( $jobs as $j )
            $rows[] = array( $j->attribute( 'id' ), $j->attribute( 'uuid' ), $j->attribute( 'type' ), $j->attribute( 'class' ), $j->attribute( 'priority' ),
                             $j->attribute( 'state' ), $j->attribute( 'attempts' ) . '/' . $j->attribute( 'max_attempts' ), $j->attribute( 'resource_key' ),
                             self::duration( expQueue::now() - (int) $j->attribute( 'created' ) ), expQueueJob::shorten( (string) $j->attribute( 'last_error' ), 50 ) );
        $this->table( $rows );
        $this->cli->output( count( $jobs ) . ' job(s)' . ( count( $jobs ) === $limit ? ' (more with --offset=' . ( $offset + $limit ) . ')' : '' ) );
        return 0;
    }

    protected function cmdShow()
    {
        $job = $this->args ? expQueue::fetch( $this->args[0] ) : null;
        if ( !$job )
        {
            $this->cli->error( 'FAIL unknown job ' . ( $this->args ? $this->args[0] : '(none given)' ) );
            return 3;
        }
        $events = expQueueEvent::fetchForJob( $job->attribute( 'id' ), 1000 );
        $events = array_slice( $events, -( !empty( $this->options['events'] ) ? (int) $this->options['events'] : 50 ) );
        if ( !empty( $this->options['json'] ) )
        {
            $data = $job->toArray();
            $data['events'] = array_map( function ( $e ) { return array( 'ts' => $e->attribute( 'ts' ), 'kind' => $e->attribute( 'kind' ),
                                                                           'attempt' => $e->attribute( 'attempt' ), 'message' => $e->attribute( 'message' ),
                                                                           'worker' => $e->attribute( 'worker' ), 'data' => $e->dataSummary() ); }, $events );
            $this->cli->output( json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
            return 0;
        }
        foreach ( $job->toArray( false ) as $key => $value )
            if ( $value !== '' && $value !== 0 && $value !== '0' && $value !== null )
                $this->cli->output( sprintf( '  %-22s %s', $key, in_array( $key, array( 'created', 'modified', 'started', 'finished', 'run_after', 'lease_until', 'ready_since', 'deadline' ), true )
                                                              ? date( 'Y-m-d H:i:s', (int) $value ) : $value ) );
        $this->cli->output( '  payload:' );
        foreach ( $job->payloadSummary() as $key => $value )
            $this->cli->output( "    $key = $value" );
        if ( $job->resultArray() )
            $this->cli->output( '  result: ' . expQueueJob::shorten( json_encode( $job->resultArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 300 ) );
        if ( $job->checkpointArray() !== null )
            $this->cli->output( '  checkpoint: stored (' . strlen( (string) $job->attribute( 'checkpoint' ) ) . ' bytes)' );
        $this->cli->output( '  events:' );
        foreach ( $events as $e )
            $this->cli->output( sprintf( '    %s  %-9s %s%s', date( 'Y-m-d H:i:s', (int) $e->attribute( 'ts' ) ), $e->attribute( 'kind' ),
                                         (int) $e->attribute( 'attempt' ) ? '#' . $e->attribute( 'attempt' ) . ' ' : '', $e->attribute( 'message' ) ) );
        return 0;
    }

    protected function cmdEnqueue()
    {
        if ( !$this->args )
        {
            $this->cli->error( 'FAIL enqueue needs a job type, e.g. ./console ext:expqueue:enqueue expqueue.noop' );
            return 1;
        }
        $type = (string) $this->args[0];
        $o = $this->options;
        $payload = array();
        if ( !empty( $o['payload'] ) )
        {
            $payload = json_decode( (string) $o['payload'], true );
            if ( !is_array( $payload ) )
            {
                $this->cli->error( 'FAIL --payload is not a JSON object' );
                return 1;
            }
        }
        $options = array();
        foreach ( array( 'class' => 'class', 'priority' => 'priority', 'group' => 'group', 'idempotency-key' => 'idempotency_key',
                         'resource' => 'resource', 'not-before' => 'not_before', 'max-attempts' => 'max_attempts', 'timeout' => 'timeout',
                         'depends-on' => 'depends_on' ) as $opt => $key )
            if ( isset( $o[$opt] ) && $o[$opt] !== false && $o[$opt] !== null && $o[$opt] !== '' )
                $options[$key] = in_array( $key, array( 'priority', 'not_before', 'max_attempts', 'timeout' ), true ) ? (int) $o[$opt] : (string) $o[$opt];
        if ( !empty( $o['new-group'] ) )
            $options['group'] = expQueue::createGroup( (string) $o['new-group'], 'cli' );
        $count = !empty( $o['count'] ) ? max( 1, (int) $o['count'] ) : 1;
        $jobs = array();
        expQueue::withLock( function () use ( $type, $payload, $options, $count, &$jobs )
        {
            for ( $i = 0; $i < $count; $i++ )
                $jobs[] = expQueue::enqueue( $type, $payload, $options );
        }, 60.0 );
        if ( !empty( $o['json'] ) )
        {
            $this->cli->output( json_encode( array_map( function ( $j ) { return $j->toArray( false ) + array( 'duplicate' => $j->duplicate ); }, $jobs ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            return 0;
        }
        foreach ( array_slice( $jobs, 0, 20 ) as $job )
            $this->cli->output( ( $job->duplicate ? 'duplicate ' : 'queued ' ) . $job->attribute( 'uuid' ) . ' (id ' . $job->attribute( 'id' ) . ', '
                                . $job->attribute( 'class' ) . ', ' . $job->attribute( 'state' ) . ')' );
        if ( $count > 20 )
            $this->cli->output( '... ' . ( $count - 20 ) . ' more' );
        if ( isset( $options['group'] ) && $options['group'] instanceof expQueueGroup )
            $this->cli->output( 'group ' . $options['group']->attribute( 'uuid' ) );
        $this->cli->output( 'PASS ' . $count . ' job(s) of ' . $type );
        return 0;
    }

    protected function cmdCancel()
    {
        $reason = !empty( $this->options['reason'] ) ? (string) $this->options['reason'] : 'cancelled from the command line';
        if ( !empty( $this->options['group'] ) )
        {
            if ( !expQueue::resolveGroup( $this->options['group'] ) )
            {
                $this->cli->error( 'FAIL unknown group ' . $this->options['group'] );
                return 3;
            }
            $n = expQueue::cancelGroup( $this->options['group'], $reason );
            $this->cli->output( "PASS cancelled the group and $n job(s)" );
            return 0;
        }
        if ( !$this->args )
        {
            $this->cli->error( 'FAIL cancel needs a job (uuid or id) or --group' );
            return 1;
        }
        $code = 0;
        foreach ( $this->args as $ref )
        {
            $state = expQueue::cancel( $ref, $reason );
            if ( $state === null )
            {
                $this->cli->output( "$ref: unknown or already ended" );
                $code = 2;
            }
            else
                $this->cli->output( "$ref: " . ( $state === 'running' ? 'cancel requested (it stops at its next check)' : 'cancelled' ) );
        }
        return $code;
    }

    protected function cmdPause()
    {
        return $this->pauseOrResume( true );
    }

    protected function cmdResume()
    {
        return $this->pauseOrResume( false );
    }

    protected function pauseOrResume( $pause )
    {
        $args = $this->args;
        if ( !$args )
        {
            $this->cli->error( 'FAIL say what: queue | class <name> | resource <key> | group <uuid>' );
            return 1;
        }
        $scope = strtolower( (string) $args[0] );
        $name = isset( $args[1] ) ? (string) $args[1] : '';
        if ( !in_array( $scope, array( 'queue', 'class', 'resource', 'group' ), true ) )
        {
            // a bare name: a class, a group uuid or a resource
            $name = (string) $args[0];
            $scope = expQueueConfig::instance()->hasClass( $name ) ? 'class' : ( preg_match( '/^[0-9a-f-]{36}$/', $name ) ? 'group' : 'resource' );
        }
        $ok = $pause ? expQueue::pause( $scope, $name ) : expQueue::resume( $scope, $name );
        $this->cli->output( ( $ok ? 'PASS ' : 'FAIL ' ) . ( $pause ? 'paused ' : 'resumed ' ) . $scope . ( $name !== '' ? " $name" : '' ) );
        return $ok ? 0 : 1;
    }

    protected function cmdRetry()
    {
        if ( !empty( $this->options['dead'] ) )
        {
            $n = expQueue::retryDead( !empty( $this->options['type'] ) ? (string) $this->options['type'] : '' );
            $this->cli->output( "PASS requeued $n dead job(s)" );
            return 0;
        }
        if ( !$this->args )
        {
            $this->cli->error( 'FAIL retry needs a job (uuid or id) or --dead' );
            return 1;
        }
        $code = 0;
        foreach ( $this->args as $ref )
        {
            $ok = expQueue::retry( $ref );
            $this->cli->output( "$ref: " . ( $ok ? 'requeued' : 'not requeued (unknown, or not dead, cancelled, held or waiting for a retry)' ) );
            if ( !$ok )
                $code = 2;
        }
        return $code;
    }

    protected function cmdPurge()
    {
        $o = $this->options;
        $r = expQueue::purge( isset( $o['days'] ) && $o['days'] !== false && $o['days'] !== null ? (int) $o['days'] : null,
                              isset( $o['dead-days'] ) && $o['dead-days'] !== false && $o['dead-days'] !== null ? (int) $o['dead-days'] : null,
                              !empty( $o['dry-run'] ), !empty( $o['type'] ) ? (string) $o['type'] : '' );
        $this->cli->output( ( !empty( $o['dry-run'] ) ? 'would remove' : 'PASS removed' ) . sprintf( ' %d done, %d cancelled, %d dead job(s)%s',
                            $r['done'], $r['cancelled'], $r['dead'], !empty( $o['dry-run'] ) ? '' : sprintf( ', %d event(s), %d group(s)', $r['events'], $r['groups'] ) ) );
        return 0;
    }

    protected function cmdInstallSchema()
    {
        $o = $this->options;
        if ( !empty( $o['print'] ) )
        {
            $statements = expQueueSchema::sqlStatements( (string) $o['print'] );
            if ( !$statements )
            {
                $this->cli->error( 'FAIL no schema handler for ' . $o['print'] );
                return 1;
            }
            foreach ( $statements as $statement )
                $this->cli->output( $statement . ( substr( $statement, -1 ) === ';' ? '' : ';' ) );
            return 0;
        }
        if ( !empty( $o['write-files'] ) )
            return $this->writeSchemaFiles();
        $db = eZDB::instance();
        $this->cli->output( 'database engine: ' . expQueueSchema::engineOf( $db ) );
        $failed = false;
        if ( empty( $o['check'] ) )
        {
            list( $created, $errors ) = expQueueSchema::ensure( $db );
            foreach ( $created as $what )
                $this->cli->output( "created $what" );
            foreach ( $errors as $error )
                $this->cli->error( "error: $error" );
            $failed = (bool) $errors;
            foreach ( array_merge( array( '*' ), array_keys( expQueueConfig::instance()->classes() ) ) as $class )
                expQueue::ensureClassRow( $class );
        }
        $missing = array_merge( expQueueSchema::missingTables( $db ), expQueueSchema::missingColumns( $db ) );
        if ( $missing )
        {
            $this->cli->output( 'FAIL missing: ' . implode( ', ', $missing ) );
            if ( !empty( $o['check'] ) )
                $this->cli->output( 'run without --check to add them' );
            return 1;
        }
        $this->cli->output( 'PASS the tables ' . implode( ', ', expQueueSchema::tables() ) . ' exist with every column and index' );
        return $failed ? 1 : 0;
    }

    protected function writeSchemaFiles()
    {
        $base = eZExtension::baseDirectory() . '/expqueue';
        $failed = false;
        $write = function ( $file, $text ) use ( &$failed )
        {
            if ( @file_put_contents( $file, $text ) === false )
            {
                $this->cli->error( "FAIL could not write $file (as " . expQueueLock::processUser() . ')' );
                $failed = true;
                return false;
            }
            return true;
        };
        if ( $write( "$base/share/db_schema.dba", expQueueSchema::dbaText() ) )
            $this->cli->output( 'PASS wrote share/db_schema.dba' );
        foreach ( array( 'mysql', 'postgresql', 'sqlite', 'oracle' ) as $engine )
        {
            $statements = expQueueSchema::sqlStatements( $engine );
            if ( !$statements )
            {
                $this->cli->error( "FAIL no schema handler for $engine" );
                $failed = true;
                continue;
            }
            $text = "-- expqueue (the Exponential job queue): the tables " . implode( ', ', expQueueSchema::tables() ) . " ($engine).\n"
                  . "-- Generated from expQueueSchema::definition() by bin/php/install-schema.php --write-files with the kernel's\n"
                  . "-- schema handler; ./console ext:expqueue:install-schema creates the same tables in a running installation.\n\n";
            foreach ( $statements as $statement )
                $text .= $statement . ( substr( $statement, -1 ) === ';' ? "\n" : ";\n" ) . ( preg_match( '/^CREATE (TABLE|OR REPLACE TRIGGER)/i', $statement ) ? "\n" : '' );
            if ( $engine === 'oracle' )
                $text = preg_replace( '/^(END;)$/m', "$1\n/", $text );
            if ( !is_dir( "$base/sql/$engine" ) )
                mkdir( "$base/sql/$engine", 0755, true );
            if ( $write( "$base/sql/$engine/schema.sql", $text ) )
                $this->cli->output( "PASS wrote sql/$engine/schema.sql (" . count( $statements ) . ' statements)' );
        }
        if ( !is_dir( "$base/sql/mongodb" ) )
            mkdir( "$base/sql/mongodb", 0755, true );
        if ( $write( "$base/sql/mongodb/schema.json", json_encode( expQueueSchema::mongoDescription(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ) )
            $this->cli->output( 'PASS wrote sql/mongodb/schema.json' );
        return $failed ? 1 : 0;
    }

    protected function cmdService()
    {
        $action = $this->args ? strtolower( (string) $this->args[0] ) : 'status';
        if ( !in_array( $action, array( 'install', 'start', 'stop', 'status', 'uninstall' ), true ) )
        {
            $this->cli->error( 'FAIL say install, start, stop, status or uninstall' );
            return 1;
        }
        if ( !empty( $this->options['instances'] ) )
            eZINI::instance( 'expqueue.ini' )->setVariable( 'ServiceSettings', 'Instances', (int) $this->options['instances'] );
        $siteaccess = isset( $this->options['siteaccess'] ) && $this->options['siteaccess'] ? (string) $this->options['siteaccess'] : '';
        $user = expQueueService::siteUser();
        $this->cli->output( 'unit ' . expQueueService::unitPath() . ', instances ' . implode( ' ', expQueueService::instanceUnits() )
                            . ', site user ' . ( $user['user'] !== '' ? $user['user'] . ':' . $user['group'] : '(none: the installation belongs to root)' ) );
        if ( !empty( $this->options['dry-run'] ) )
        {
            foreach ( expQueueService::plan( $action, $siteaccess ) as $step )
            {
                if ( isset( $step['write'] ) )
                    $this->cli->output( "would write {$step['write']}:\n" . preg_replace( '/^/m', '    ', rtrim( $step['text'] ) ) );
                elseif ( isset( $step['remove'] ) )
                    $this->cli->output( "would remove {$step['remove']}" );
                else
                    $this->cli->output( 'would run: ' . implode( ' ', $step['run'] ) );
            }
            $this->cli->output( 'PASS dry run: nothing written or started' );
            return 0;
        }
        $cli = $this->cli;
        $code = expQueueService::perform( $action, $siteaccess, function ( $line ) use ( $cli ) { $cli->output( $line ); } );
        if ( $action === 'status' )
        {
            $summary = expQueueService::statusSummary();
            $this->cli->output( 'workers of the service alive (heartbeat): ' . $summary['workers'] );
        }
        $this->cli->output( ( $code === 0 ? 'PASS ' : 'FAIL ' ) . $action );
        return $code;
    }

    // ---- helpers ----------------------------------------------------------------------------------------------------

    /** @return string[] a comma-separated option as a list */
    protected function listOption( $name )
    {
        if ( empty( $this->options[$name] ) )
            return array();
        return array_values( array_filter( array_map( 'trim', explode( ',', (string) $this->options[$name] ) ), 'strlen' ) );
    }

    /** Prints rows as an aligned table (the first row is the header). */
    protected function table( array $rows )
    {
        $widths = array();
        foreach ( $rows as $row )
            foreach ( array_values( $row ) as $i => $cell )
                $widths[$i] = max( isset( $widths[$i] ) ? $widths[$i] : 0, mb_strlen( (string) $cell ) );
        foreach ( $rows as $n => $row )
        {
            $line = '';
            foreach ( array_values( $row ) as $i => $cell )
                $line .= str_pad( (string) $cell, $widths[$i] + 2 + strlen( (string) $cell ) - mb_strlen( (string) $cell ) );
            $this->cli->output( rtrim( $line ) );
        }
    }

    /** @return string seconds as 45s, 12m, 3h, 2d */
    public static function duration( $seconds )
    {
        $seconds = (int) $seconds;
        if ( $seconds < 120 )
            return $seconds . 's';
        if ( $seconds < 7200 )
            return round( $seconds / 60 ) . 'm';
        if ( $seconds < 172800 )
            return round( $seconds / 3600 ) . 'h';
        return round( $seconds / 86400 ) . 'd';
    }
}
