<?php
/**
 * File containing the expQueueStats class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The figures of the queue for the dashboard (expqueue/dashboard) and ./console ext:expqueue:status: counts per state,
 * per class and per resource, the oldest waiting job per class, throughput and waits of the last hours, the workers,
 * the last cron run and the systemd service.
 */
class expQueueStats
{
    /**
     * @return array states, totals, classes, resources, throughput, hourly, workers, cron, service, paused, dead_recent,
     *               running_jobs, generated
     */
    public static function report()
    {
        $now = expQueue::now();
        $config = expQueueConfig::instance();
        $states = expQueue::countBy( 'state' );
        foreach ( expQueueJob::states() as $state )
            if ( !isset( $states[$state] ) )
                $states[$state] = 0;

        $ready = expQueue::countBy( 'class', array( 'state' => expQueueJob::STATE_READY ) );
        $running = expQueue::countBy( 'class', array( 'state' => expQueueJob::STATE_RUNNING ) );
        $waiting = expQueue::countBy( 'class', array( 'state' => array( expQueueJob::STATE_QUEUED, expQueueJob::STATE_RETRY, expQueueJob::STATE_HELD ) ) );
        $dead = expQueue::countBy( 'class', array( 'state' => expQueueJob::STATE_DEAD ) );
        $rows = expQueue::classRows();
        $hour = self::lastHour( $now );
        $classes = array();
        foreach ( $config->classes() as $name => $def )
        {
            $oldest = 0;
            if ( !empty( $ready[$name] ) )
            {
                $o = expQueue::db()->arrayQuery( 'SELECT ready_since FROM expqueue_job WHERE ' . expQueue::whereSql( array( 'state' => 'ready', 'class' => $name ) )
                                                 . ' ORDER BY ready_since ASC', array( 'limit' => 1 ) );
                if ( is_array( $o ) && isset( $o[0] ) )
                    $oldest = max( 0, $now - (int) reset( $o[0] ) );
            }
            $waits = isset( $hour['waits'][$name] ) ? $hour['waits'][$name] : array();
            $classes[$name] = array(
                'name' => $name,
                'weight' => $def['weight'],
                'max_running' => $def['max_running'],
                'min_share' => $def['min_share'],
                'reserved' => $def['reserved'],
                'ready' => isset( $ready[$name] ) ? $ready[$name] : 0,
                'running' => isset( $running[$name] ) ? $running[$name] : 0,
                'waiting' => isset( $waiting[$name] ) ? $waiting[$name] : 0,
                'dead' => isset( $dead[$name] ) ? $dead[$name] : 0,
                'oldest_wait' => $oldest,
                'paused' => isset( $rows[$name] ) ? (bool) $rows[$name]['paused'] : false,
                'done_hour' => isset( $hour['done'][$name] ) ? $hour['done'][$name] : 0,
                'wait_p50' => self::percentile( $waits, 50 ),
                'wait_p95' => self::percentile( $waits, 95 ),
            );
        }
        $runningByResource = expQueue::countBy( 'resource_key', array( 'state' => expQueueJob::STATE_RUNNING ) );
        $readyByResource = expQueue::countBy( 'resource_key', array( 'state' => expQueueJob::STATE_READY ) );
        $resources = array();
        foreach ( expQueueResource::all() as $key => $r )
            $resources[$key] = $r + array( 'running' => isset( $runningByResource[$key] ) ? $runningByResource[$key] : 0,
                                           'ready' => isset( $readyByResource[$key] ) ? $readyByResource[$key] : 0,
                                           'cooling' => $r['cooldown_until'] > $now ? $r['cooldown_until'] - $now : 0 );
        $workers = expQueueWorker::live();
        $active = 0;
        foreach ( expQueueJob::activeStates() as $s )
            $active += $states[$s];
        return array(
            'generated' => $now,
            'installed' => true,
            'paused' => expQueue::isPaused( 'queue' ),
            'paused_by_setting' => $config->enabled( 'QueueSettings', 'Paused', false ),
            'states' => $states,
            'totals' => array( 'active' => $active, 'all' => array_sum( $states ),
                               'done_hour' => array_sum( $hour['done'] ), 'dead_hour' => $hour['dead'],
                               'oldest_wait' => $classes ? max( array_map( function ( $c ) { return $c['oldest_wait']; }, $classes ) ) : 0,
                               'workers' => count( $workers ) ),
            'classes' => $classes,
            'resources' => $resources,
            'hourly' => self::hourly( $now ),
            'workers' => $workers,
            'recent_workers' => expQueueWorker::recent( 10 ),
            'cron' => self::cronStatus( $now ),
            'service' => expQueueService::statusSummary(),
            'dead_recent' => expQueue::listJobs( array( 'state' => expQueueJob::STATE_DEAD ), 0, 5 ),
            'running_jobs' => expQueue::listJobs( array( 'state' => expQueueJob::STATE_RUNNING ), 0, 10 ),
            'groups' => expQueueGroup::fetchRecent( 5 ),
        );
    }

    /** @return array done (class => count), dead (count), waits (class => seconds[]) of the jobs started or ended in the last hour */
    protected static function lastHour( $now )
    {
        $out = array( 'done' => array(), 'dead' => 0, 'waits' => array() );
        $rows = expQueue::db()->arrayQuery( 'SELECT class, state, ready_since, started, finished FROM expqueue_job WHERE '
                                            . expQueue::whereSql( array( 'started' => array( '>=', $now - 3600 ) ) ), array( 'limit' => 20000 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $r )
        {
            $r = array_change_key_case( $r, CASE_LOWER );
            $class = (string) $r['class'];
            if ( (int) $r['ready_since'] > 0 && (int) $r['started'] >= (int) $r['ready_since'] )
                $out['waits'][$class][] = (int) $r['started'] - (int) $r['ready_since'];
            if ( (int) $r['finished'] >= $now - 3600 )
            {
                if ( $r['state'] === expQueueJob::STATE_DONE )
                    $out['done'][$class] = ( isset( $out['done'][$class] ) ? $out['done'][$class] : 0 ) + 1;
                elseif ( $r['state'] === expQueueJob::STATE_DEAD )
                    $out['dead']++;
            }
        }
        return $out;
    }

    /**
     * Jobs that ended per hour over the last 24 hours (the dashboard's one chart).
     *
     * @return array[] list of hour (timestamp of its start), label (HH), done, dead, max (the largest done + dead)
     */
    public static function hourly( $now )
    {
        $start = (int) ( floor( $now / 3600 ) * 3600 ) - 23 * 3600;
        $buckets = array();
        for ( $i = 0; $i < 24; $i++ )
            $buckets[$i] = array( 'hour' => $start + $i * 3600, 'label' => date( 'H', $start + $i * 3600 ), 'done' => 0, 'dead' => 0 );
        $rows = expQueue::db()->arrayQuery( 'SELECT state, finished FROM expqueue_job WHERE '
                                            . expQueue::whereSql( array( 'state' => array( 'done', 'dead' ), 'finished' => array( '>=', $start ) ) ),
                                            array( 'limit' => 100000 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $r )
        {
            $r = array_change_key_case( $r, CASE_LOWER );
            $i = (int) floor( ( (int) $r['finished'] - $start ) / 3600 );
            if ( $i >= 0 && $i < 24 )
                $buckets[$i][$r['state'] === 'dead' ? 'dead' : 'done']++;
        }
        $max = 0;
        foreach ( $buckets as $b )
            $max = max( $max, $b['done'] + $b['dead'] );
        foreach ( $buckets as $i => $b )
        {
            $buckets[$i]['max'] = $max;
            $buckets[$i]['done_pct'] = $max ? round( 100 * $b['done'] / $max, 1 ) : 0;
            $buckets[$i]['dead_pct'] = $max ? round( 100 * $b['dead'] / $max, 1 ) : 0;
        }
        return array_values( $buckets );
    }

    /** @return int|null the p-th percentile of a list of numbers, null when it is empty */
    public static function percentile( array $values, $p )
    {
        if ( !$values )
            return null;
        sort( $values );
        $i = (int) ceil( $p / 100 * count( $values ) ) - 1;
        return (int) $values[max( 0, min( count( $values ) - 1, $i ) )];
    }

    /** Notes that the cron part ran (the row 'cron' of expqueue_mutex: expires = the time of the run). */
    public static function markCronRun( array $summary = array() )
    {
        $db = expQueue::db();
        $now = expQueue::now();
        $owner = $db->escapeString( substr( json_encode( $summary ), 0, 80 ) );
        $rows = $db->arrayQuery( "SELECT name FROM expqueue_mutex WHERE name = 'cron'" );
        if ( !is_array( $rows ) || !$rows )
            $db->query( "INSERT INTO expqueue_mutex ( name, owner, expires, modified ) VALUES ( 'cron', '$owner', $now, $now )" );
        else
            $db->query( "UPDATE expqueue_mutex SET owner = '$owner', expires = $now, modified = $now WHERE name = 'cron'" );
    }

    /** @return array last (timestamp or 0), ago (seconds), ok (ran within the last 3 minutes), summary */
    public static function cronStatus( $now = null )
    {
        $now = $now ?: expQueue::now();
        $rows = expQueue::db()->arrayQuery( "SELECT owner, expires FROM expqueue_mutex WHERE name = 'cron'" );
        $row = is_array( $rows ) && isset( $rows[0] ) ? array_change_key_case( $rows[0], CASE_LOWER ) : array();
        $last = $row ? (int) $row['expires'] : 0;
        $summary = $row ? json_decode( (string) $row['owner'], true ) : null;
        return array( 'last' => $last, 'ago' => $last ? $now - $last : null, 'ok' => $last && $now - $last <= 180,
                      'summary' => is_array( $summary ) ? $summary : array() );
    }
}
