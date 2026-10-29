<?php
/**
 * File containing the expQueueScheduler class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * Chooses the next job for a worker and leases it to that worker; keeps the queue moving (maintenance).
 *
 * A dispatch round (claim()), under the dispatcher lock:
 *   1. The eligible classes: those with a ready job (run_after passed), not paused, below their MaxRunning (0 = no
 *      limit), in the worker's --class filter.
 *   2. Their order:
 *        a) Reserved classes (system) first: they have their own slot and do not compete.
 *        b) Starvation guard: a class whose oldest ready job has waited longer than StarvationSeconds, the longest
 *           waiting first.
 *        c) MinShare: a class that got less than its MinShare of the jobs started in the last MinShareWindow
 *           seconds (once at least 10 were started), the largest deficit first.
 *        d) Stride scheduling: the smallest pass first; the chosen class's pass grows by 100000 / Weight. The pass is
 *           stored in expqueue_class, so the shares hold across all workers. A class that was idle comes back at
 *           the current virtual time (the row '*'), not with the credit of its idle time.
 *   3. Within the class: a job that waited longer than StarvationSeconds first (the longest waiting), else the
 *      highest effective priority = priority + min(AgingMax, waited / AgingStep), then the longest waiting. Up to
 *      CandidateLimit candidates are tried.
 *   4. A candidate is skipped when its resource is paused, cooling down or full, its group is paused, or its user or
 *      site has MaxRunningPerUser / MaxRunningPerSite jobs running.
 *   5. The claim is a compare-and-set update (state 'ready' and the attempts read) that writes the lease, read back:
 *      a second worker that read the same row loses the update and tries the next candidate.
 *
 * maintenance() (every MaintenanceInterval seconds in every worker, and in the cron part): retry and not-before jobs
 * whose time has come become ready, queued jobs whose parent is done become ready, running jobs whose lease expired
 * (a worker that was killed) go back to ready, or to the dead letter when their attempts are used up, and workers
 * without a heartbeat are marked lost.
 */
class expQueueScheduler
{
    const STRIDE = 100000;

    /** @var array worker filter: classes, resources */
    protected $filter;

    /** @var array cache of the shares: at, data */
    protected $shares = array( 'at' => 0, 'data' => array(), 'total' => 0 );

    /** @var int when maintenance() last ran in this process */
    protected $lastMaintenance = 0;

    /** @var array the decisions of the last claim (tests, --verbose) */
    public $lastDecision = array();

    /** @var array claims tried, won and lost to another worker (the compare-and-set lost) in this process */
    public $counters = array( 'rounds' => 0, 'won' => 0, 'lost' => 0, 'empty' => 0 );

    public function __construct( array $filter = array() )
    {
        $this->filter = array(
            'classes' => isset( $filter['classes'] ) ? array_values( array_filter( (array) $filter['classes'], 'strlen' ) ) : array(),
            'resources' => isset( $filter['resources'] ) ? array_values( array_filter( (array) $filter['resources'], 'strlen' ) ) : array(),
        );
    }

    /**
     * Chooses and leases the next job.
     *
     * @param string $owner the lease owner (worker id and claim number)
     * @return expQueueJob|null
     */
    public function claim( $owner )
    {
        if ( !expQueueLock::acquire( 'dispatch', 10.0 ) )
            return null;
        try
        {
            return $this->claimLocked( $owner );
        }
        finally
        {
            expQueueLock::release( 'dispatch' );
        }
    }

    protected function claimLocked( $owner )
    {
        $config = expQueueConfig::instance();
        $db = expQueue::db();
        $now = expQueue::now();
        expQueueResource::clearCache();
        $this->lastDecision = array();
        $this->counters['rounds']++;
        if ( expQueue::isPaused( 'queue' ) )
        {
            $this->lastDecision['reason'] = 'the queue is paused';
            return null;
        }
        $classes = $config->classes();
        $rows = expQueue::classRows();
        $vt = isset( $rows['*'] ) ? $rows['*']['stride_pass'] : 0;

        // what runs now, per class, resource, user and site
        $running = array( 'class' => array(), 'resource' => array(), 'user' => array(), 'site' => array() );
        $list = $db->arrayQuery( "SELECT class, resource_key, user_id, site FROM expqueue_job WHERE state = 'running'" );
        foreach ( is_array( $list ) ? $list : array() as $r )
        {
            $r = array_change_key_case( $r, CASE_LOWER );
            foreach ( array( 'class' => 'class', 'resource' => 'resource_key', 'user' => 'user_id', 'site' => 'site' ) as $k => $f )
            {
                $v = (string) $r[$f];
                $running[$k][$v] = ( isset( $running[$k][$v] ) ? $running[$k][$v] : 0 ) + 1;
            }
        }

        // 1. the eligible classes
        $eligible = array();
        foreach ( $classes as $name => $def )
        {
            if ( $this->filter['classes'] && !in_array( $name, $this->filter['classes'], true ) )
                continue;
            if ( isset( $rows[$name] ) && $rows[$name]['paused'] )
                continue;
            $n = isset( $running['class'][$name] ) ? $running['class'][$name] : 0;
            if ( $def['max_running'] > 0 && $n >= $def['max_running'] )
                continue;
            $where = array( 'state' => expQueueJob::STATE_READY, 'class' => $name, 'run_after' => array( '<=', $now ) );
            if ( $this->filter['resources'] )
                $where['resource_key'] = $this->filter['resources'];
            $oldest = $db->arrayQuery( 'SELECT id, ready_since FROM expqueue_job WHERE ' . expQueue::whereSql( $where ) . ' ORDER BY ready_since ASC', array( 'limit' => 1 ) );
            if ( !is_array( $oldest ) || !$oldest )
                continue;
            $o = array_change_key_case( $oldest[0], CASE_LOWER );
            $pass = isset( $rows[$name] ) ? $rows[$name]['stride_pass'] : 0;
            $eligible[$name] = array( 'def' => $def, 'wait' => max( 0, $now - (int) $o['ready_since'] ), 'pass' => max( $pass, $vt ),
                                      'stored_pass' => $pass, 'where' => $where );
        }
        if ( !$eligible )
        {
            $this->counters['empty']++;
            $this->lastDecision['reason'] = 'no ready job';
            return null;
        }

        // 2. the order of the classes
        $starvation = $config->queueInt( 'StarvationSeconds', 1 );
        $shares = $this->shares( array_keys( $classes ) );
        $order = array();
        foreach ( $eligible as $name => $e )
        {
            $deficit = 0.0;
            if ( $shares['total'] >= 10 && $e['def']['min_share'] > 0 )
            {
                $share = ( isset( $shares['data'][$name] ) ? $shares['data'][$name] : 0 ) / $shares['total'];
                $deficit = max( 0.0, $e['def']['min_share'] - $share );
            }
            if ( $e['def']['reserved'] )
                $tier = 0;
            elseif ( $e['wait'] > $starvation )
                $tier = 1;
            elseif ( $deficit > 0 )
                $tier = 2;
            else
                $tier = 3;
            $order[$name] = array( $tier, $tier === 1 ? -$e['wait'] : ( $tier === 2 ? -$deficit : $e['pass'] ), -$e['def']['weight'] );
        }
        uksort( $order, function ( $a, $b ) use ( $order )
        {
            return $order[$a] <=> $order[$b] ?: strcmp( $a, $b );
        } );

        // 3.-5. the candidates of each class in turn
        $quotas = $config->quotas();
        $groups = array();
        $tried = 0;
        foreach ( array_keys( $order ) as $name )
        {
            $e = $eligible[$name];
            foreach ( $this->candidates( $e['where'], $now ) as $c )
            {
                if ( ++$tried > 4 * $config->queueInt( 'CandidateLimit', 1 ) )
                    break 2;
                $resource = (string) $c['resource_key'];
                if ( $resource !== '' && expQueueResource::blocked( $resource, isset( $running['resource'][$resource] ) ? $running['resource'][$resource] : 0 ) !== null )
                    continue;
                $gid = (int) $c['group_id'];
                if ( $gid )
                {
                    if ( !isset( $groups[$gid] ) )
                    {
                        $g = expQueueGroup::fetchByID( $gid );
                        $groups[$gid] = $g ? $g->attribute( 'state' ) : 'active';
                    }
                    if ( $groups[$gid] === expQueueGroup::STATE_PAUSED )
                        continue;
                }
                $uid = (string) (int) $c['user_id'];
                if ( $quotas['MaxRunningPerUser'] > 0 && ( isset( $running['user'][$uid] ) ? $running['user'][$uid] : 0 ) >= $quotas['MaxRunningPerUser'] )
                    continue;
                $site = (string) $c['site'];
                if ( $quotas['MaxRunningPerSite'] > 0 && ( isset( $running['site'][$site] ) ? $running['site'][$site] : 0 ) >= $quotas['MaxRunningPerSite'] )
                    continue;
                $job = $this->lease( $c, $owner, $now );
                if ( !$job )
                {
                    $this->counters['lost']++;
                    continue;
                }
                $this->counters['won']++;
                $this->advance( $name, $e, $vt );
                $this->lastDecision = array( 'class' => $name, 'tier' => $order[$name][0], 'pass' => $e['pass'], 'job' => (int) $c['id'] );
                return $job;
            }
        }
        $this->lastDecision['reason'] = 'every ready job is blocked (resource, group or quota)';
        return null;
    }

    /**
     * The candidates of a class: the highest priorities and the longest waiting, ordered by effective priority.
     *
     * @return array rows
     */
    protected function candidates( array $where, $now )
    {
        $config = expQueueConfig::instance();
        $db = expQueue::db();
        $limit = $config->queueInt( 'CandidateLimit', 1 );
        $fields = 'id, priority, ready_since, attempts, timeout, resource_key, group_id, user_id, site';
        $sql = "SELECT $fields FROM expqueue_job WHERE " . expQueue::whereSql( $where );
        $rows = array();
        foreach ( array( ' ORDER BY priority DESC, id ASC', ' ORDER BY ready_since ASC, id ASC' ) as $orderBy )
        {
            $list = $db->arrayQuery( $sql . $orderBy, array( 'limit' => $limit ) );
            foreach ( is_array( $list ) ? $list : array() as $r )
            {
                $r = array_change_key_case( $r, CASE_LOWER );
                $rows[(int) $r['id']] = $r;
            }
        }
        $step = $config->queueInt( 'AgingStep', 1 );
        $max = $config->queueInt( 'AgingMax' );
        foreach ( $rows as $id => $r )
            $rows[$id]['effective'] = (int) $r['priority'] + min( $max, (int) floor( max( 0, $now - (int) $r['ready_since'] ) / $step ) );
        // a job that waited longer than StarvationSeconds gets the next slot of its class whatever its priority (aging
        // alone is capped by AgingMax); among those the longest waiting first
        $starving = $now - $config->queueInt( 'StarvationSeconds', 1 );
        uasort( $rows, function ( $a, $b ) use ( $starving )
        {
            $sa = (int) $a['ready_since'] < $starving;
            $sb = (int) $b['ready_since'] < $starving;
            if ( $sa !== $sb )
                return $sa ? -1 : 1;
            if ( $sa )
                return (int) $a['ready_since'] <=> (int) $b['ready_since'] ?: (int) $a['id'] <=> (int) $b['id'];
            return $b['effective'] <=> $a['effective'] ?: ( (int) $a['ready_since'] <=> (int) $b['ready_since'] ?: (int) $a['id'] <=> (int) $b['id'] );
        } );
        return array_slice( array_values( $rows ), 0, $limit );
    }

    /**
     * Leases one candidate: compare-and-set on state and attempts, then read back.
     *
     * @return expQueueJob|null
     */
    protected function lease( array $c, $owner, $now )
    {
        $id = (int) $c['id'];
        $attempts = (int) $c['attempts'];
        $until = $now + max( 1, (int) $c['timeout'] ) + expQueueConfig::instance()->queueInt( 'LeaseMargin' );
        $ok = expQueue::casUpdate( $id, array( 'state' => expQueueJob::STATE_READY, 'attempts' => $attempts ), array(
            'state' => expQueueJob::STATE_RUNNING, 'lease_owner' => (string) $owner, 'lease_until' => $until,
            'attempts' => $attempts + 1, 'started' => $now, 'finished' => 0 ) );
        if ( !$ok )
            return null;
        $job = expQueueJob::fetchByID( $id );
        if ( $job )
            expQueueEvent::add( $id, $job->attribute( 'group_id' ), 'claim', 'started, attempt ' . ( $attempts + 1 ) . ' of ' . $job->attribute( 'max_attempts' ),
                                array( 'wait' => max( 0, $now - (int) $c['ready_since'] ) ), $attempts + 1, (string) $owner );
        return $job;
    }

    /** The stride step of the chosen class, and the virtual time. */
    protected function advance( $name, array $e, $vt )
    {
        $db = expQueue::db();
        $now = expQueue::now();
        $stride = (int) ceil( self::STRIDE / max( 0.01, $e['def']['weight'] ) );
        $effective = max( $e['stored_pass'], $vt );
        expQueue::ensureClassRow( $name );
        expQueue::ensureClassRow( '*' );
        $n = $db->escapeString( $name );
        $db->query( 'UPDATE expqueue_class SET stride_pass = ' . ( $effective + $stride ) . ", modified = $now WHERE name = '$n'" );
        if ( $effective > $vt )
            $db->query( "UPDATE expqueue_class SET stride_pass = $effective, modified = $now WHERE name = '*'" );
        // keep the numbers small (32-bit columns): shift everything down once the virtual time is large
        if ( $effective > 1000000000 )
        {
            foreach ( expQueue::classRows() as $class => $row )
                $db->query( 'UPDATE expqueue_class SET stride_pass = ' . max( 0, $row['stride_pass'] - $effective )
                            . " WHERE name = '" . $db->escapeString( $class ) . "'" );
        }
    }

    /**
     * The jobs started per class in the last MinShareWindow seconds (cached for 10 seconds).
     *
     * @return array data: class => count, total
     */
    protected function shares( array $classes )
    {
        $now = expQueue::now();
        if ( $this->shares['at'] > $now - 10 )
            return $this->shares;
        $window = expQueueConfig::instance()->queueInt( 'MinShareWindow', 1 );
        $data = expQueue::countBy( 'class', array( 'started' => array( '>=', $now - $window ) ) );
        $data = array_intersect_key( $data, array_flip( $classes ) );
        return $this->shares = array( 'at' => $now, 'data' => $data, 'total' => array_sum( $data ) );
    }

    /**
     * Keeps the queue moving: promotes, reaps, marks lost workers.
     *
     * @param bool $force run even when it ran less than MaintenanceInterval seconds ago
     * @return array promoted, reaped, dead, lost: what it did
     */
    public function maintenance( $force = false )
    {
        $now = expQueue::now();
        $out = array( 'promoted' => 0, 'reaped' => 0, 'dead' => 0, 'lost' => 0 );
        if ( !$force && $now - $this->lastMaintenance < expQueueConfig::instance()->queueInt( 'MaintenanceInterval', 1 ) )
            return $out;
        $this->lastMaintenance = $now;
        if ( !expQueueLock::acquire( 'dispatch', 10.0 ) )
            return $out;
        try
        {
            return self::maintain( $now );
        }
        finally
        {
            expQueueLock::release( 'dispatch' );
        }
    }

    /** The maintenance itself, with the lock held. */
    public static function maintain( $now )
    {
        $db = expQueue::db();
        $out = array( 'promoted' => 0, 'reaped' => 0, 'dead' => 0, 'lost' => 0 );

        // retry and not-before jobs whose time has come
        $out['promoted'] += expQueue::count( array( 'state' => expQueueJob::STATE_RETRY, 'run_after' => array( '<=', $now ) ) )
                          + expQueue::count( array( 'state' => expQueueJob::STATE_QUEUED, 'parent_id' => 0, 'run_after' => array( '<=', $now ) ) );
        $db->query( "UPDATE expqueue_job SET state = 'ready', ready_since = $now, modified = $now WHERE state = 'retry' AND run_after <= $now" );
        $db->query( "UPDATE expqueue_job SET state = 'ready', ready_since = $now, modified = $now WHERE state = 'queued' AND parent_id = 0 AND run_after <= $now" );

        // queued jobs whose parent ended while they still waited for their not-before time
        $rows = $db->arrayQuery( "SELECT id, parent_id, group_id FROM expqueue_job WHERE state = 'queued' AND parent_id > 0 AND run_after <= $now", array( 'limit' => 200 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $r )
        {
            $r = array_change_key_case( $r, CASE_LOWER );
            $parent = expQueueJob::fetchByID( (int) $r['parent_id'] );
            $ps = $parent ? $parent->attribute( 'state' ) : expQueueJob::STATE_CANCELLED;
            if ( $ps === expQueueJob::STATE_DONE )
            {
                if ( expQueue::casUpdate( (int) $r['id'], array( 'state' => expQueueJob::STATE_QUEUED ), array( 'state' => expQueueJob::STATE_READY, 'ready_since' => $now ) ) )
                {
                    expQueueEvent::add( $r['id'], $r['group_id'], 'ready', 'its parent job is done' );
                    $out['promoted']++;
                }
            }
            elseif ( in_array( $ps, array( expQueueJob::STATE_DEAD, expQueueJob::STATE_CANCELLED ), true ) )
            {
                if ( expQueue::casUpdate( (int) $r['id'], array( 'state' => expQueueJob::STATE_QUEUED ),
                                          array( 'state' => expQueueJob::STATE_CANCELLED, 'finished' => $now, 'last_error' => "its parent job ended $ps" ) ) )
                    expQueueEvent::add( $r['id'], $r['group_id'], 'cancel', "its parent job ended $ps" );
            }
        }

        // leases that expired: the worker died (kill -9, a reboot, out of memory)
        $rows = $db->arrayQuery( "SELECT id, attempts, max_attempts, cancel_requested, lease_owner, group_id, type, uuid FROM expqueue_job WHERE state = 'running' AND lease_until < $now",
                                 array( 'limit' => 500 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $r )
        {
            $r = array_change_key_case( $r, CASE_LOWER );
            $where = array( 'state' => expQueueJob::STATE_RUNNING, 'lease_owner' => (string) $r['lease_owner'] );
            if ( (int) $r['cancel_requested'] )
            {
                $set = array( 'state' => expQueueJob::STATE_CANCELLED, 'finished' => $now, 'lease_until' => 0, 'last_error' => 'cancelled; its worker stopped' );
                $kind = 'cancel';
            }
            elseif ( (int) $r['attempts'] >= (int) $r['max_attempts'] )
            {
                $set = array( 'state' => expQueueJob::STATE_DEAD, 'finished' => $now, 'lease_until' => 0, 'error_class' => 'lease_expired',
                              'last_error' => 'the lease expired (the worker stopped or the job ran past its timeout) and no attempt is left' );
                $kind = 'dead';
            }
            else
            {
                $set = array( 'state' => expQueueJob::STATE_READY, 'ready_since' => $now, 'lease_until' => 0, 'run_after' => 0, 'error_class' => 'lease_expired',
                              'last_error' => 'the lease expired (the worker stopped or the job ran past its timeout); it runs again' );
                $kind = 'reap';
            }
            if ( expQueue::casUpdate( (int) $r['id'], $where, $set ) )
            {
                expQueueEvent::add( $r['id'], $r['group_id'], $kind, $set['last_error'], array( 'lease_owner' => (string) $r['lease_owner'] ), (int) $r['attempts'] );
                $out[$kind === 'reap' ? 'reaped' : 'dead']++;
                if ( $kind !== 'reap' )
                {
                    $job = expQueueJob::fetchByID( (int) $r['id'] );
                    if ( $job )
                        expQueue::afterFinal( $job );
                    if ( $kind === 'dead' )
                        expQueue::audit( 'job.dead', array( 'job' => (string) $r['uuid'], 'type' => (string) $r['type'], 'error' => 'lease_expired' ) );
                }
            }
        }

        // workers without a heartbeat
        $stale = $now - max( 30, (int) expQueueConfig::instance()->worker( 'StaleSeconds', 90 ) );
        $out['lost'] = count( (array) $db->arrayQuery( "SELECT worker_id FROM expqueue_worker WHERE state = 'running' AND heartbeat < $stale" ) );
        if ( $out['lost'] )
            $db->query( "UPDATE expqueue_worker SET state = 'lost', modified = $now WHERE state = 'running' AND heartbeat < $stale" );
        // stopped and lost workers are kept for a day, for the dashboard
        $db->query( "DELETE FROM expqueue_worker WHERE state <> 'running' AND heartbeat < " . ( $now - 86400 ) );
        return $out;
    }
}
