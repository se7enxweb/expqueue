<?php
/**
 * The cron part "expqueue" (php runcronjobs.php -q expqueue, every minute): the default worker of the queue and the
 * safety net next to a systemd service.
 *
 *   1. maintenance: retry and not-before jobs become ready, expired leases are reaped, lost workers marked;
 *   2. once an hour: the purge of old jobs (KeepDays, KeepDeadDays);
 *   3. unless another worker is alive ([WorkerSettings] CronSkipWhenWorkerAlive): a worker slice of CronMaxSeconds
 *      seconds that stops as soon as nothing is ready. Run as root, the slice is started as its own process, which
 *      becomes the site user (a cron job of root must not write the queue's files as root).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/** @var eZCLI $cli */
/** @var bool $isQuiet */
$say = function ( $line ) use ( $cli, $isQuiet ) { if ( !$isQuiet ) $cli->output( 'expqueue: ' . $line ); };
if ( !expQueue::installed() )
{
    $cli->error( 'expqueue: the tables are missing: run ./console ext:expqueue:install-schema' );
    return;
}
$config = expQueueConfig::instance();
$scheduler = new expQueueScheduler();
$m = $scheduler->maintenance( true );
$say( sprintf( 'maintenance: %d promoted, %d reaped, %d dead, %d lost worker(s)', $m['promoted'], $m['reaped'], $m['dead'], $m['lost'] ) );

// the purge, at most once an hour (the row 'purge' of expqueue_mutex holds the time of the last one)
$db = expQueue::db();
$rows = $db->arrayQuery( "SELECT expires FROM expqueue_mutex WHERE name = 'purge'" );
$last = is_array( $rows ) && isset( $rows[0] ) ? (int) reset( $rows[0] ) : -1;
if ( $last < expQueue::now() - 3600 )
{
    $now = expQueue::now();
    if ( $last < 0 )
        $db->query( "INSERT INTO expqueue_mutex ( name, owner, expires, modified ) VALUES ( 'purge', 'cron', $now, $now )" );
    else
        $db->query( "UPDATE expqueue_mutex SET expires = $now, modified = $now WHERE name = 'purge'" );
    $p = expQueue::purge();
    $say( sprintf( 'purge: %d done, %d cancelled, %d dead job(s) removed', $p['done'], $p['cancelled'], $p['dead'] ) );
}

$summary = $m;
$alive = expQueueWorker::live();
if ( $alive && $config->enabled( 'WorkerSettings', 'CronSkipWhenWorkerAlive', true ) )
{
    $say( count( $alive ) . ' worker(s) alive (' . implode( ', ', array_unique( array_map( function ( $w ) { return $w['origin']; }, $alive ) ) ) . '): no slice' );
    $summary['slice'] = 'skipped';
}
elseif ( expQueue::count( array( 'state' => expQueueJob::STATE_READY ) ) === 0 )
{
    $say( 'nothing ready' );
    $summary['slice'] = 'empty';
}
elseif ( function_exists( 'posix_geteuid' ) && posix_geteuid() === 0 )
{
    // as root: a worker process of its own, which becomes the site user before it touches anything
    $php = trim( (string) $config->worker( 'PhpBinary', '' ) ) ?: PHP_BINARY;
    $argv = array( $php, eZSys::rootDir() . '/extension/expqueue/bin/php/work.php', '--origin=cron', '--until-empty',
                   '--max-seconds=' . max( 5, (int) $config->worker( 'CronMaxSeconds', 50 ) ), '-q' );
    $pipes = array();
    $proc = @proc_open( $argv, array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes, eZSys::rootDir() );
    $rc = is_resource( $proc ) ? proc_close( $proc ) : -1;
    $say( "worker slice as the site user: exit $rc" );
    $summary['slice'] = 'process';
}
else
{
    $refusal = expQueueWorker::refusal();
    if ( $refusal === null )
    {
        $worker = new expQueueWorker( array( 'origin' => 'cron', 'until_empty' => true, 'max_jobs' => 0,
                                             'max_seconds' => max( 5, (int) $config->worker( 'CronMaxSeconds', 50 ) ) ) );
        if ( !$isQuiet )
            $worker->output = function ( $line ) use ( $cli ) { $cli->output( 'expqueue: ' . $line ); };
        $r = $worker->run();
        $say( sprintf( 'worker slice: %d job(s) in %.1f s (%d done, %d retry, %d dead), left: %s', $r['jobs'], $r['seconds'], $r['done'], $r['retry'], $r['dead'], $r['stopped'] ) );
        $summary['slice'] = $r['jobs'];
    }
    else
        $say( $refusal );
}
expQueueStats::markCronRun( array( 'p' => $summary['promoted'], 'r' => $summary['reaped'], 's' => isset( $summary['slice'] ) ? $summary['slice'] : '' ) );
