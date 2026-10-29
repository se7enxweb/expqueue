<?php
/**
 * File containing the expQueue class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The job queue: what producers call (enqueue, groups, cancel, pause/resume, retry, results) and the state changes the
 * workers and the admin make. A generic queue: it knows nothing about what a job does. A job type is registered in
 * expqueue.ini ([JobType_<type>] Handler=<class>); its handler runs in a worker process (CLI, cron or systemd), never
 * in a web request.
 *
 *   $job = expQueue::enqueue( 'mysite.thumbnail', array( 'node_id' => 42 ), array(
 *       'class' => 'editorial', 'priority' => 60, 'group' => $group, 'idempotency_key' => 'thumb:42:3',
 *       'resource' => 'imaging', 'not_before' => 300, 'max_attempts' => 3, 'timeout' => 120 ) );
 *   $job->attribute( 'uuid' );       // the public id (URLs, CLI, MCP)
 *   expQueue::fetch( $uuid )->resultArray();
 *
 * Every state change is a compare-and-set update (the expected state in the WHERE clause), read back afterwards
 * because eZDB reports no affected rows; every change writes an event row. The decisions that need a consistent view
 * (idempotency, quotas, a dispatch round) run under the dispatcher lock (expQueueLock).
 */
class expQueue
{
    /** @var expQueueHandler[] type => handler instance */
    protected static $handlers = array();

    /** @var callable|null replaces time() (tests) */
    public static $clock = null;

    /** @var bool false keeps a sandbox or a test out of the installation's audit trail */
    public static $auditEnabled = true;

    // ---- basics -----------------------------------------------------------------------------------------------

    /** @return int the current time */
    public static function now()
    {
        return self::$clock ? (int) call_user_func( self::$clock ) : time();
    }

    /** @return eZDBInterface */
    public static function db()
    {
        return eZDB::instance();
    }

    /** @return string a random UUID (version 4) */
    public static function uuid()
    {
        $b = random_bytes( 16 );
        $b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 );
        $b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 );
        $h = bin2hex( $b );
        return substr( $h, 0, 8 ) . '-' . substr( $h, 8, 4 ) . '-' . substr( $h, 12, 4 ) . '-' . substr( $h, 16, 4 ) . '-' . substr( $h, 20 );
    }

    /** @return bool whether the queue's tables exist in the current database */
    public static function installed()
    {
        static $known = array();
        $db = self::db();
        $key = spl_object_id( $db );
        if ( !isset( $known[$key] ) )
            $known[$key] = !expQueueSchema::missingTables( $db );
        return $known[$key];
    }

    /**
     * The handler of a job type.
     *
     * @param string $type
     * @param bool $throw throw when the type is unknown or its class is missing
     * @return expQueueHandler|null
     */
    public static function handlerFor( $type, $throw = true )
    {
        if ( isset( self::$handlers[$type] ) )
            return self::$handlers[$type];
        $def = expQueueConfig::instance()->jobType( $type );
        if ( !$def )
        {
            if ( $throw )
                throw new expQueuePermanentException( "unknown job type $type (no [JobType_$type] Handler= in expqueue.ini)", 'unknown_type' );
            return null;
        }
        $class = $def['handler'];
        if ( !class_exists( $class ) || !is_subclass_of( $class, 'expQueueHandler' ) )
        {
            if ( $throw )
                throw new expQueuePermanentException( "the handler $class of $type does not exist or does not implement expQueueHandler", 'no_handler' );
            return null;
        }
        return self::$handlers[$type] = new $class();
    }

    /** Registers a handler instance for a type in this process (tests; types defined in code). */
    public static function setHandler( $type, ?expQueueHandler $handler )
    {
        if ( $handler === null )
            unset( self::$handlers[$type] );
        else
            self::$handlers[$type] = $handler;
    }

    /**
     * Runs $code with the dispatcher lock held (reentrant): wrap a loop of enqueue() calls in it to take the lock once.
     *
     * @param callable $code
     * @param float $wait
     * @return mixed what $code returns
     */
    public static function withLock( $code, $wait = 15.0 )
    {
        if ( !expQueueLock::acquire( 'dispatch', $wait ) )
            throw new expQueueException( 'the queue is busy: the dispatcher lock could not be taken' );
        try
        {
            return call_user_func( $code );
        }
        finally
        {
            expQueueLock::release( 'dispatch' );
        }
    }

    // ---- producers ----------------------------------------------------------------------------------------------

    /**
     * Queues a job.
     *
     * Options (all optional):
     *   class            priority class (expqueue.ini [QueueSettings] Classes[]); default: the type's, else DefaultClass
     *   priority         0..100 within the class (default 50 or the class's DefaultPriority)
     *   group            an expQueueGroup, its id or its uuid
     *   idempotency_key  a string unique per type among active jobs and done jobs younger than IdempotencyWindow;
     *                    queueing the same key again returns the existing job with $job->duplicate = true
     *   resource         the concurrency key, e.g. "anthropic:standard" (limits in [QueueResource_<key>])
     *   not_before       a timestamp, or seconds from now when below 10^9
     *   max_attempts     attempts before the dead letter (default: type, class, then DefaultMaxAttempts)
     *   timeout          seconds a run may take before its lease expires (default: type, then DefaultTimeout)
     *   depends_on       a job (object, id or uuid) that must be done first
     *   user_id          the user the job runs as (default: the current user)
     *   site             for the per-site quotas (default: site.ini [SiteSettings] SiteName)
     *   deadline         a timestamp the job should be done by (for producers that choose a faster lane)
     *   lane, cost_estimate_micros, external_ref    stored as given, for producers that use them
     *   tags             strings the rules of [QueueRules] can match (Tag=)
     *
     * @param string $type
     * @param array $payload JSON-serialisable parameters; never a secret (the admin shows them, masked or not)
     * @param array $options
     * @return expQueueJob
     * @throws expQueueException unknown type or class, invalid payload, quota reached
     */
    public static function enqueue( $type, array $payload = array(), array $options = array() )
    {
        $type = (string) $type;
        $config = expQueueConfig::instance();
        $def = $config->jobType( $type );
        if ( !$def && !isset( self::$handlers[$type] ) )
            throw new expQueueException( "unknown job type $type: register it in expqueue.ini as [JobType_$type] Handler=<class>" );
        $def = $def ?: array( 'class' => '', 'priority' => null, 'max_attempts' => 0, 'timeout' => 0, 'resource' => '' );
        $handler = self::handlerFor( $type, false );
        if ( $handler instanceof expQueueHandlerBase )
        {
            $error = $handler->validate( $payload );
            if ( $error !== null && $error !== '' )
                throw new expQueueException( "invalid payload for $type: $error" );
        }
        $json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( $json === false )
            throw new expQueueException( 'the payload cannot be encoded as JSON: ' . json_last_error_msg() );

        // defaults of the type, then the rules, then what the producer asked for
        $resolved = array( 'class' => $def['class'], 'priority' => $def['priority'], 'resource' => $def['resource'],
                           'max_attempts' => $def['max_attempts'] );
        $tags = isset( $options['tags'] ) ? array_map( 'strval', (array) $options['tags'] ) : array();
        $site = isset( $options['site'] ) ? (string) $options['site'] : self::currentSite();
        $resourceAsked = isset( $options['resource'] ) ? (string) $options['resource'] : $resolved['resource'];
        foreach ( $config->rules() as $rule )
        {
            if ( $rule['type'] !== '' && !fnmatch( $rule['type'], $type ) )
                continue;
            if ( $rule['resource'] !== '' && !fnmatch( $rule['resource'], $resourceAsked ) )
                continue;
            if ( $rule['site'] !== '' && $rule['site'] !== $site )
                continue;
            if ( $rule['tag'] !== '' && !in_array( $rule['tag'], $tags, true ) )
                continue;
            if ( $rule['set_class'] !== '' )
                $resolved['class'] = $rule['set_class'];
            if ( $rule['set_priority'] !== null && $rule['set_priority'] !== '' )
                $resolved['priority'] = (int) $rule['set_priority'];
            if ( $rule['set_resource'] !== '' )
                $resolved['resource'] = $rule['set_resource'];
            if ( $rule['set_max_attempts'] > 0 )
                $resolved['max_attempts'] = $rule['set_max_attempts'];
            $resolved['rule'] = $rule['name'];
            break;
        }
        foreach ( array( 'class', 'priority', 'resource', 'max_attempts' ) as $key )
            if ( isset( $options[$key] ) && $options[$key] !== '' )
                $resolved[$key] = $options[$key];

        $class = (string) $resolved['class'] !== '' ? (string) $resolved['class'] : $config->defaultClass();
        if ( !$config->hasClass( $class ) )
            throw new expQueueException( "unknown priority class $class (expqueue.ini [QueueSettings] Classes[])" );
        $classDef = $config->classes()[$class];
        $priority = $resolved['priority'] === null || $resolved['priority'] === '' ? $classDef['default_priority'] : (int) $resolved['priority'];
        $priority = min( 100, max( 0, $priority ) );
        $maxAttempts = (int) $resolved['max_attempts'] ?: ( $classDef['max_attempts'] ?: $config->queueInt( 'DefaultMaxAttempts', 1 ) );
        $timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 0;
        $timeout = $timeout > 0 ? $timeout : ( $def['timeout'] ?: $config->queueInt( 'DefaultTimeout', 1 ) );
        $resource = substr( preg_replace( '/[^A-Za-z0-9_.:@\/-]/', '', (string) $resolved['resource'] ), 0, 80 );

        $now = self::now();
        $runAfter = 0;
        if ( isset( $options['not_before'] ) && (int) $options['not_before'] > 0 )
        {
            $nb = (int) $options['not_before'];
            $runAfter = $nb < 1000000000 ? $now + $nb : $nb;
        }
        $userID = isset( $options['user_id'] ) ? (int) $options['user_id'] : self::currentUserID();
        $key = isset( $options['idempotency_key'] ) ? (string) $options['idempotency_key'] : '';
        if ( strlen( $key ) > 64 )
            $key = sha1( $key );

        return self::withLock( function () use ( $type, $json, $options, $class, $priority, $maxAttempts, $timeout, $resource,
                                                 $runAfter, $userID, $site, $key, $now, $resolved )
        {
            if ( $key !== '' )
            {
                $existing = self::findByIdempotencyKey( $type, $key );
                if ( $existing )
                {
                    $existing->duplicate = true;
                    expQueueEvent::add( $existing->attribute( 'id' ), $existing->attribute( 'group_id' ), 'duplicate',
                                        'a job with the same idempotency key was queued again' );
                    return $existing;
                }
            }
            self::checkQueueQuotas( $userID, $site );

            $groupID = 0;
            if ( isset( $options['group'] ) && $options['group'] )
            {
                $group = self::resolveGroup( $options['group'] );
                if ( !$group )
                    throw new expQueueException( 'unknown job group ' . ( is_object( $options['group'] ) ? '' : (string) $options['group'] ) );
                if ( $group->attribute( 'state' ) === expQueueGroup::STATE_CANCELLED )
                    throw new expQueueException( 'the job group ' . $group->attribute( 'uuid' ) . ' is cancelled' );
                $groupID = (int) $group->attribute( 'id' );
            }
            $parentID = 0;
            $waitForParent = false;
            if ( isset( $options['depends_on'] ) && $options['depends_on'] )
            {
                $parent = self::fetch( $options['depends_on'] );
                if ( !$parent )
                    throw new expQueueException( 'unknown parent job ' . ( is_object( $options['depends_on'] ) ? '' : (string) $options['depends_on'] ) );
                $parentID = (int) $parent->attribute( 'id' );
                $ps = $parent->attribute( 'state' );
                if ( $ps === expQueueJob::STATE_DEAD || $ps === expQueueJob::STATE_CANCELLED )
                    throw new expQueueException( "the parent job ended $ps" );
                $waitForParent = $ps !== expQueueJob::STATE_DONE;
            }
            $state = ( $waitForParent || $runAfter > $now ) ? expQueueJob::STATE_QUEUED : expQueueJob::STATE_READY;
            $job = new expQueueJob( array(
                'uuid' => self::uuid(),
                'type' => $type,
                'class' => $class,
                'priority' => $priority,
                'state' => $state,
                'group_id' => $groupID,
                'parent_id' => $parentID,
                'resource_key' => $resource,
                'lane' => isset( $options['lane'] ) ? substr( (string) $options['lane'], 0, 16 ) : '',
                'site' => substr( $site, 0, 60 ),
                'user_id' => $userID,
                'idempotency_key' => $key,
                'attempts' => 0,
                'max_attempts' => max( 1, $maxAttempts ),
                'timeout' => max( 1, $timeout ),
                'run_after' => $runAfter,
                'ready_since' => $state === expQueueJob::STATE_READY ? $now : 0,
                'deadline' => isset( $options['deadline'] ) ? (int) $options['deadline'] : 0,
                'external_ref' => isset( $options['external_ref'] ) ? substr( (string) $options['external_ref'], 0, 120 ) : '',
                'cost_estimate_micros' => isset( $options['cost_estimate_micros'] ) ? (int) $options['cost_estimate_micros'] : 0,
                'payload' => $json,
                'created' => $now,
                'modified' => $now,
            ) );
            $job->store();
            if ( !(int) $job->attribute( 'id' ) )
                throw new expQueueException( 'the job could not be stored (database error)' );
            if ( $groupID )
                self::db()->query( 'UPDATE expqueue_group SET total = total + 1, modified = ' . $now . ' WHERE id = ' . $groupID );
            $data = array( 'class' => $class, 'priority' => $priority, 'resource' => $resource );
            if ( isset( $resolved['rule'] ) )
                $data['rule'] = $resolved['rule'];
            expQueueEvent::add( $job->attribute( 'id' ), $groupID, 'enqueue', $state === expQueueJob::STATE_QUEUED
                                ? ( $waitForParent ? 'queued, waits for its parent job' : 'queued, not before ' . date( 'Y-m-d H:i:s', $runAfter ) )
                                : 'queued, ready', $data );
            return $job;
        } );
    }

    /**
     * Creates a job group.
     *
     * @param string $label shown in the admin
     * @param string $type a free tag for the producer (e.g. "translate.subtree")
     * @param array $options user_id, site, budget_micros
     * @return expQueueGroup
     */
    public static function createGroup( $label, $type = '', array $options = array() )
    {
        $now = self::now();
        $group = new expQueueGroup( array(
            'uuid' => self::uuid(),
            'label' => expQueueJob::shorten( (string) $label, 255 ),
            'type' => substr( (string) $type, 0, 60 ),
            'user_id' => isset( $options['user_id'] ) ? (int) $options['user_id'] : self::currentUserID(),
            'site' => substr( isset( $options['site'] ) ? (string) $options['site'] : self::currentSite(), 0, 60 ),
            'state' => expQueueGroup::STATE_ACTIVE,
            'total' => 0,
            'budget_micros' => isset( $options['budget_micros'] ) ? (int) $options['budget_micros'] : 0,
            'created' => $now,
            'modified' => $now,
        ) );
        $group->store();
        return $group;
    }

    /**
     * A job by uuid or id (or the job itself).
     *
     * @param expQueueJob|string|int $ref
     * @return expQueueJob|null
     */
    public static function fetch( $ref )
    {
        if ( $ref instanceof expQueueJob )
            return $ref->reload();
        $ref = trim( (string) $ref );
        if ( preg_match( '/^[0-9a-f-]{36}$/', $ref ) )
            return expQueueJob::fetchByUUID( $ref );
        if ( ctype_digit( $ref ) )
            return expQueueJob::fetchByID( (int) $ref );
        return null;
    }

    /** @return expQueueGroup|null a group by object, id or uuid */
    public static function resolveGroup( $ref )
    {
        if ( $ref instanceof expQueueGroup )
            return $ref;
        $ref = trim( (string) $ref );
        if ( preg_match( '/^[0-9a-f-]{36}$/', $ref ) )
            return expQueueGroup::fetchByUUID( $ref );
        if ( ctype_digit( $ref ) )
            return expQueueGroup::fetchByID( (int) $ref );
        return null;
    }

    /** @return expQueueJob|null an active job, or a recently done one, with this type and idempotency key */
    public static function findByIdempotencyKey( $type, $key )
    {
        $db = self::db();
        $window = expQueueConfig::instance()->queueInt( 'IdempotencyWindow' );
        $rows = $db->arrayQuery( "SELECT id, state, finished FROM expqueue_job WHERE type = '" . $db->escapeString( $type )
                                 . "' AND idempotency_key = '" . $db->escapeString( $key ) . "' ORDER BY id DESC", array( 'limit' => 20 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $state = (string) $row['state'];
            if ( in_array( $state, expQueueJob::activeStates(), true )
                 || ( $state === expQueueJob::STATE_DONE && (int) $row['finished'] >= self::now() - $window ) )
                return expQueueJob::fetchByID( (int) $row['id'] );
        }
        return null;
    }

    /** Throws when the user or the site has as many queued jobs as [QueueQuotas] allows. */
    protected static function checkQueueQuotas( $userID, $site )
    {
        $quotas = expQueueConfig::instance()->quotas();
        $waiting = array( expQueueJob::STATE_QUEUED, expQueueJob::STATE_READY, expQueueJob::STATE_RETRY, expQueueJob::STATE_HELD );
        if ( $quotas['MaxQueuedPerUser'] > 0
             && array_sum( self::countBy( 'state', array( 'user_id' => (int) $userID, 'state' => $waiting ) ) ) >= $quotas['MaxQueuedPerUser'] )
            throw new expQueueException( 'quota reached: the user has ' . $quotas['MaxQueuedPerUser'] . ' jobs waiting (MaxQueuedPerUser)' );
        if ( $quotas['MaxQueuedPerSite'] > 0
             && array_sum( self::countBy( 'state', array( 'site' => (string) $site, 'state' => $waiting ) ) ) >= $quotas['MaxQueuedPerSite'] )
            throw new expQueueException( 'quota reached: the site has ' . $quotas['MaxQueuedPerSite'] . ' jobs waiting (MaxQueuedPerSite)' );
    }

    /** @return int the current user's id (the anonymous user in a script without a login) */
    public static function currentUserID()
    {
        $id = 0;
        if ( class_exists( 'eZUser' ) )
            $id = (int) eZUser::currentUserID();
        if ( !$id )
            $id = (int) eZINI::instance()->variable( 'UserSettings', 'AnonymousUserID' );
        return $id;
    }

    /** @return string the site of the current request (site.ini [SiteSettings] SiteName) */
    public static function currentSite()
    {
        $name = (string) expQueueConfig::instance()->value( 'QueueSettings', 'SiteName', '' );
        if ( $name === '' )
            $name = (string) eZINI::instance()->variable( 'SiteSettings', 'SiteName' );
        return substr( $name, 0, 60 );
    }

    // ---- state changes ------------------------------------------------------------------------------------------

    /**
     * A compare-and-set update of one job: $set is written only when every condition of $where holds, then the row
     * is read back to see whether it was this update that won.
     *
     * @param int $id
     * @param array $where field => value (or list of values)
     * @param array $set field => value
     * @return bool whether the row now has the values of $set
     */
    public static function casUpdate( $id, array $where, array $set )
    {
        $db = self::db();
        $set['modified'] = isset( $set['modified'] ) ? $set['modified'] : self::now();
        $parts = array();
        foreach ( $set as $field => $value )
            $parts[] = $field . ' = ' . self::sqlValue( $value );
        $where = array( 'id' => (int) $id ) + $where;
        if ( $db->query( 'UPDATE expqueue_job SET ' . implode( ', ', $parts ) . ' WHERE ' . self::whereSql( $where ) ) === false )
            return false;
        $check = array_diff_key( $set, array( 'modified' => 1, 'payload' => 1, 'checkpoint' => 1, 'result' => 1, 'last_error' => 1, 'progress_message' => 1 ) );
        // the conditions decide: check the fields of $where that $set does not change, so the read-back proves the
        // row matched (a field set to the value it already had proves nothing on its own)
        if ( !$check )
            $check = array_diff_key( $where, array( 'id' => 1 ) );
        if ( !$check )
            return true;
        $rows = $db->arrayQuery( 'SELECT ' . implode( ', ', array_keys( $check ) ) . ' FROM expqueue_job WHERE id = ' . (int) $id );
        if ( !is_array( $rows ) || !isset( $rows[0] ) )
            return false;
        $row = array_change_key_case( $rows[0], CASE_LOWER );
        foreach ( $check as $field => $value )
            if ( (string) $row[$field] !== (string) $value )
                return false;
        return true;
    }

    /** @return string a value as an SQL literal */
    public static function sqlValue( $value )
    {
        if ( is_int( $value ) || is_float( $value ) )
            return (string) (int) $value;
        if ( is_bool( $value ) )
            return $value ? '1' : '0';
        return "'" . self::db()->escapeString( (string) $value ) . "'";
    }

    /**
     * A WHERE clause from conditions: field => value, field => list (IN), field => array( '<'|'<='|'>'|'>='|'!=', value ).
     *
     * @return string
     */
    public static function whereSql( array $where )
    {
        $parts = array();
        foreach ( $where as $field => $value )
        {
            if ( !preg_match( '/^[a-z_]+$/', $field ) )
                continue;
            if ( is_array( $value ) && isset( $value[0] ) && is_string( $value[0] ) && in_array( $value[0], array( '<', '<=', '>', '>=', '!=' ), true ) && count( $value ) === 2 )
                $parts[] = $field . ' ' . ( $value[0] === '!=' ? '<>' : $value[0] ) . ' ' . self::sqlValue( $value[1] );
            elseif ( is_array( $value ) )
            {
                if ( !$value )
                    $parts[] = '1 = 0';
                elseif ( count( $value ) === 1 )
                    $parts[] = $field . ' = ' . self::sqlValue( reset( $value ) );
                else
                    $parts[] = $field . ' IN ( ' . implode( ', ', array_map( array( 'expQueue', 'sqlValue' ), $value ) ) . ' )';
            }
            else
                $parts[] = $field . ' = ' . self::sqlValue( $value );
        }
        return $parts ? implode( ' AND ', $parts ) : '1 = 1';
    }

    /**
     * Counts jobs by one column (state, class, resource_key, type, ...).
     *
     * @param string $field
     * @param array $where see whereSql()
     * @return array value => count
     */
    public static function countBy( $field, array $where = array() )
    {
        if ( !preg_match( '/^[a-z_]+$/', $field ) )
            return array();
        $db = self::db();
        $out = array();
        if ( expQueueSchema::engineOf( $db ) === 'mongodb' )
        {
            // the MongoDB driver translates no GROUP BY: count in PHP
            $rows = $db->arrayQuery( "SELECT $field FROM expqueue_job WHERE " . self::whereSql( $where ) );
            foreach ( is_array( $rows ) ? $rows : array() as $row )
            {
                $row = array_change_key_case( $row, CASE_LOWER );
                $v = (string) $row[$field];
                $out[$v] = ( isset( $out[$v] ) ? $out[$v] : 0 ) + 1;
            }
            return $out;
        }
        $rows = $db->arrayQuery( "SELECT $field AS v, COUNT(*) AS n FROM expqueue_job WHERE " . self::whereSql( $where ) . " GROUP BY $field" );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out[(string) $row['v']] = (int) $row['n'];
        }
        return $out;
    }

    /** @return int the number of jobs matching the conditions */
    public static function count( array $where = array() )
    {
        $rows = self::db()->arrayQuery( 'SELECT COUNT(*) AS n FROM expqueue_job WHERE ' . self::whereSql( $where ) );
        if ( !is_array( $rows ) || !isset( $rows[0] ) )
            return 0;
        $row = array_change_key_case( $rows[0], CASE_LOWER );
        return (int) $row['n'];
    }

    /**
     * Cancels a job: one that waits ends cancelled at once; one that runs gets a cancel request, which its handler
     * honours at its next throwIfCancelled() (or the job ends when it finishes).
     *
     * @param expQueueJob|string|int $ref
     * @param string $reason
     * @return string|null the new state (cancelled, or running with a cancel request), null when it had ended
     */
    public static function cancel( $ref, $reason = '' )
    {
        $job = self::fetch( $ref );
        if ( !$job || $job->isFinal() )
            return null;
        $id = (int) $job->attribute( 'id' );
        $message = 'cancelled' . ( $reason !== '' ? ': ' . $reason : '' );
        foreach ( array( expQueueJob::STATE_QUEUED, expQueueJob::STATE_READY, expQueueJob::STATE_RETRY, expQueueJob::STATE_HELD ) as $state )
        {
            if ( self::casUpdate( $id, array( 'state' => $state ), array( 'state' => expQueueJob::STATE_CANCELLED, 'finished' => self::now(),
                                                                        'last_error' => expQueueJob::shorten( $message, 255 ) ) ) )
            {
                expQueueEvent::add( $id, $job->attribute( 'group_id' ), 'cancel', $message, array( 'from' => $state ) );
                self::afterFinal( $job );
                self::audit( 'job.cancel', array( 'job' => $job->attribute( 'uuid' ), 'type' => $job->attribute( 'type' ), 'from' => $state ) );
                return expQueueJob::STATE_CANCELLED;
            }
        }
        if ( self::casUpdate( $id, array( 'state' => expQueueJob::STATE_RUNNING ), array( 'cancel_requested' => 1 ) ) )
        {
            expQueueEvent::add( $id, $job->attribute( 'group_id' ), 'cancel', 'cancel requested while it runs' . ( $reason !== '' ? ': ' . $reason : '' ) );
            self::audit( 'job.cancel', array( 'job' => $job->attribute( 'uuid' ), 'type' => $job->attribute( 'type' ), 'from' => 'running' ) );
            return expQueueJob::STATE_RUNNING;
        }
        return null;
    }

    /**
     * Puts a dead, cancelled, held or retrying job back into the queue with its attempts reset ("requeue").
     *
     * @param expQueueJob|string|int $ref
     * @return bool
     */
    public static function retry( $ref )
    {
        $job = self::fetch( $ref );
        if ( !$job || !$job->canRetry() )
            return false;
        $from = $job->attribute( 'state' );
        $now = self::now();
        $ok = self::casUpdate( (int) $job->attribute( 'id' ), array( 'state' => $from ), array(
            'state' => expQueueJob::STATE_READY, 'attempts' => 0, 'run_after' => 0, 'ready_since' => $now, 'finished' => 0,
            'cancel_requested' => 0, 'lease_owner' => '', 'lease_until' => 0, 'error_class' => '' ) );
        if ( $ok )
        {
            expQueueEvent::add( $job->attribute( 'id' ), $job->attribute( 'group_id' ), 'requeue', "requeued from $from, attempts reset" );
            if ( $job->attribute( 'group_id' ) )
                self::db()->query( "UPDATE expqueue_group SET state = 'active', modified = $now WHERE id = " . (int) $job->attribute( 'group_id' ) . " AND state = 'done'" );
            self::audit( 'job.retry', array( 'job' => $job->attribute( 'uuid' ), 'type' => $job->attribute( 'type' ), 'from' => $from ) );
        }
        return $ok;
    }

    /**
     * Puts every dead job (of a type, when given) back into the queue.
     *
     * @return int how many
     */
    public static function retryDead( $type = '' )
    {
        $where = array( 'state' => expQueueJob::STATE_DEAD );
        if ( $type !== '' )
            $where['type'] = $type;
        $n = 0;
        $rows = self::db()->arrayQuery( 'SELECT id FROM expqueue_job WHERE ' . self::whereSql( $where ) . ' ORDER BY id', array( 'limit' => 5000 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            if ( self::retry( (int) $row['id'] ) )
                $n++;
        }
        return $n;
    }

    /**
     * Pauses a class, a resource, a group or the whole queue. A paused scope keeps its jobs; the scheduler only stops
     * starting new ones (jobs already running finish).
     *
     * @param string $scope class | resource | group | queue
     * @param string $name the class name, resource key, group uuid/id ('' for the queue)
     * @return bool
     */
    public static function pause( $scope, $name = '' )
    {
        return self::setPaused( $scope, $name, true );
    }

    /** @see pause() */
    public static function resume( $scope, $name = '' )
    {
        return self::setPaused( $scope, $name, false );
    }

    protected static function setPaused( $scope, $name, $paused )
    {
        $db = self::db();
        $now = self::now();
        $flag = $paused ? 1 : 0;
        switch ( $scope )
        {
            case 'queue':
                $name = '*';
                // no break: the queue is the class row '*'
            case 'class':
                if ( $scope === 'class' && !expQueueConfig::instance()->hasClass( $name ) )
                    return false;
                self::ensureClassRow( $name );
                $ok = $db->query( "UPDATE expqueue_class SET paused = $flag, modified = $now WHERE name = '" . $db->escapeString( $name ) . "'" ) !== false;
                break;
            case 'resource':
                if ( $name === '' )
                    return false;
                expQueueResource::ensure( $name );
                $ok = $db->query( "UPDATE expqueue_resource SET paused = $flag, modified = $now WHERE resource_key = '" . $db->escapeString( $name ) . "'" ) !== false;
                break;
            case 'group':
                $group = self::resolveGroup( $name );
                if ( !$group || in_array( $group->attribute( 'state' ), array( expQueueGroup::STATE_CANCELLED, expQueueGroup::STATE_DONE ), true ) )
                    return false;
                $group->setAttribute( 'state', $paused ? expQueueGroup::STATE_PAUSED : expQueueGroup::STATE_ACTIVE );
                $group->setAttribute( 'modified', $now );
                $group->store();
                // held jobs of a resumed group run again
                if ( !$paused )
                    $db->query( "UPDATE expqueue_job SET state = 'ready', ready_since = $now, modified = $now WHERE group_id = "
                                . (int) $group->attribute( 'id' ) . " AND state = 'held'" );
                $name = $group->attribute( 'uuid' );
                $ok = true;
                break;
            default:
                return false;
        }
        if ( $ok )
            self::audit( $paused ? 'pause' : 'resume', array( 'scope' => $scope, 'name' => (string) $name ) );
        return $ok;
    }

    /**
     * Cancels every job of a group that has not ended, and the group.
     *
     * @return int the number of jobs cancelled or asked to cancel
     */
    public static function cancelGroup( $ref, $reason = '' )
    {
        $group = self::resolveGroup( $ref );
        if ( !$group )
            return 0;
        $group->setAttribute( 'state', expQueueGroup::STATE_CANCELLED );
        $group->setAttribute( 'modified', self::now() );
        $group->store();
        $n = 0;
        $rows = self::db()->arrayQuery( 'SELECT id FROM expqueue_job WHERE ' . self::whereSql( array( 'group_id' => (int) $group->attribute( 'id' ),
                                                                                                      'state' => expQueueJob::activeStates() ) ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            if ( self::cancel( (int) $row['id'], $reason !== '' ? $reason : 'the group was cancelled' ) !== null )
                $n++;
        }
        self::audit( 'group.cancel', array( 'group' => $group->attribute( 'uuid' ), 'jobs' => $n ) );
        return $n;
    }

    /** @return bool whether a scope is paused (the queue, a class, a resource) */
    public static function isPaused( $scope, $name = '' )
    {
        if ( $scope === 'queue' && expQueueConfig::instance()->enabled( 'QueueSettings', 'Paused', false ) )
            return true;
        $rows = $scope === 'resource'
              ? self::db()->arrayQuery( "SELECT paused FROM expqueue_resource WHERE resource_key = '" . self::db()->escapeString( $name ) . "'" )
              : self::db()->arrayQuery( "SELECT paused FROM expqueue_class WHERE name = '" . self::db()->escapeString( $scope === 'queue' ? '*' : $name ) . "'" );
        if ( !is_array( $rows ) || !isset( $rows[0] ) )
            return false;
        $row = array_change_key_case( $rows[0], CASE_LOWER );
        return (int) $row['paused'] === 1;
    }

    /** Creates the row of a class (or '*', the queue) in expqueue_class when it is missing. */
    public static function ensureClassRow( $name )
    {
        $db = self::db();
        $n = $db->escapeString( (string) $name );
        $rows = $db->arrayQuery( "SELECT name FROM expqueue_class WHERE name = '$n'" );
        if ( !is_array( $rows ) || !$rows )
            $db->query( "INSERT INTO expqueue_class ( name, paused, stride_pass, modified ) VALUES ( '$n', 0, 0, " . self::now() . ' )' );
    }

    /** @return array name => row (paused, stride_pass) of expqueue_class */
    public static function classRows()
    {
        $out = array();
        $rows = self::db()->arrayQuery( 'SELECT name, paused, stride_pass FROM expqueue_class' );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out[(string) $row['name']] = array( 'paused' => (int) $row['paused'], 'stride_pass' => (int) $row['stride_pass'] );
        }
        return $out;
    }

    /**
     * After a job reached a final state: the jobs that waited for it can run (or are cancelled when it did not end
     * done), and its group is done when no active job is left.
     */
    public static function afterFinal( expQueueJob $job )
    {
        $db = self::db();
        $now = self::now();
        $fresh = $job->reload();
        $state = $fresh ? $fresh->attribute( 'state' ) : $job->attribute( 'state' );
        $children = $db->arrayQuery( 'SELECT id, group_id, run_after FROM expqueue_job WHERE parent_id = ' . (int) $job->attribute( 'id' ) . " AND state = 'queued'" );
        foreach ( is_array( $children ) ? $children : array() as $child )
        {
            $child = array_change_key_case( $child, CASE_LOWER );
            if ( $state === expQueueJob::STATE_DONE )
            {
                if ( (int) $child['run_after'] <= $now
                     && self::casUpdate( (int) $child['id'], array( 'state' => expQueueJob::STATE_QUEUED ), array( 'state' => expQueueJob::STATE_READY, 'ready_since' => $now ) ) )
                    expQueueEvent::add( $child['id'], $child['group_id'], 'ready', 'its parent job is done' );
            }
            elseif ( self::casUpdate( (int) $child['id'], array( 'state' => expQueueJob::STATE_QUEUED ),
                                      array( 'state' => expQueueJob::STATE_CANCELLED, 'finished' => $now, 'last_error' => "its parent job ended $state" ) ) )
            {
                expQueueEvent::add( $child['id'], $child['group_id'], 'cancel', "its parent job ended $state" );
                $c = expQueueJob::fetchByID( (int) $child['id'] );
                if ( $c )
                    self::afterFinal( $c );
            }
        }
        $groupID = (int) $job->attribute( 'group_id' );
        if ( $groupID && self::count( array( 'group_id' => $groupID, 'state' => expQueueJob::activeStates() ) ) === 0 )
            $db->query( "UPDATE expqueue_group SET state = 'done', modified = $now WHERE id = $groupID AND state IN ( 'active', 'paused' )" );
    }

    /**
     * Removes done and cancelled jobs older than KeepDays and dead jobs older than KeepDeadDays (with their events).
     *
     * @param int|null $days null = KeepDays
     * @param int|null $deadDays null = KeepDeadDays
     * @param bool $dryRun only count
     * @param string $type only jobs of this type
     * @return array done, cancelled, dead, events, groups: what was (or would be) removed
     */
    public static function purge( $days = null, $deadDays = null, $dryRun = false, $type = '' )
    {
        $config = expQueueConfig::instance();
        $days = $days === null ? $config->queueInt( 'KeepDays' ) : max( 0, (int) $days );
        $deadDays = $deadDays === null ? $config->queueInt( 'KeepDeadDays' ) : max( 0, (int) $deadDays );
        $now = self::now();
        $db = self::db();
        $out = array( 'done' => 0, 'cancelled' => 0, 'dead' => 0, 'events' => 0, 'groups' => 0 );
        $sets = array( 'done' => $days, 'cancelled' => $days, 'dead' => $deadDays );
        foreach ( $sets as $state => $keep )
        {
            $where = array( 'state' => $state, 'finished' => array( '<', $now - $keep * 86400 + 1 ) );
            if ( $type !== '' )
                $where['type'] = $type;
            while ( true )
            {
                $rows = $db->arrayQuery( 'SELECT id FROM expqueue_job WHERE ' . self::whereSql( $where ) . ' ORDER BY id', array( 'limit' => 500 ) );
                if ( !is_array( $rows ) || !$rows )
                    break;
                $ids = array();
                foreach ( $rows as $row )
                {
                    $row = array_change_key_case( $row, CASE_LOWER );
                    $ids[] = (int) $row['id'];
                }
                $out[$state] += count( $ids );
                if ( $dryRun )
                {
                    $out[$state] = self::count( $where );
                    break;
                }
                $list = implode( ', ', $ids );
                $events = $db->arrayQuery( "SELECT COUNT(*) AS n FROM expqueue_event WHERE job_id IN ( $list )" );
                $out['events'] += is_array( $events ) && isset( $events[0] ) ? (int) reset( $events[0] ) : 0;
                $db->begin();
                $db->query( "DELETE FROM expqueue_event WHERE job_id IN ( $list )" );
                $db->query( "DELETE FROM expqueue_job WHERE id IN ( $list )" );
                $db->commit();
                if ( count( $ids ) < 500 )
                    break;
            }
        }
        if ( !$dryRun )
        {
            // groups that ended and have no job left
            $groups = $db->arrayQuery( "SELECT id FROM expqueue_group WHERE state IN ( 'done', 'cancelled' ) AND modified < " . ( $now - $days * 86400 + 1 ) );
            foreach ( is_array( $groups ) ? $groups : array() as $g )
            {
                $g = array_change_key_case( $g, CASE_LOWER );
                if ( self::count( array( 'group_id' => (int) $g['id'] ) ) === 0 )
                {
                    $db->query( 'DELETE FROM expqueue_group WHERE id = ' . (int) $g['id'] );
                    $out['groups']++;
                }
            }
            if ( array_sum( $out ) > 0 )
                self::audit( 'purge', $out + array( 'keep_days' => $days, 'keep_dead_days' => $deadDays ) );
        }
        return $out;
    }

    /**
     * Jobs for a list (admin, CLI).
     *
     * @param array $filter state, class, type, resource_key, group_id, user_id (each a value or a list)
     * @return expQueueJob[]
     */
    public static function listJobs( array $filter = array(), $offset = 0, $limit = 50 )
    {
        $conds = array();
        foreach ( array( 'state', 'class', 'type', 'resource_key', 'group_id', 'user_id' ) as $key )
            if ( isset( $filter[$key] ) && $filter[$key] !== '' && $filter[$key] !== array() )
                $conds[$key] = is_array( $filter[$key] ) ? $filter[$key] : ( in_array( $key, array( 'group_id', 'user_id' ), true ) ? (int) $filter[$key] : (string) $filter[$key] );
        $rows = self::db()->arrayQuery( 'SELECT id FROM expqueue_job WHERE ' . self::whereSql( $conds ) . ' ORDER BY id DESC',
                                        array( 'offset' => max( 0, (int) $offset ), 'limit' => max( 1, (int) $limit ) ) );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $job = expQueueJob::fetchByID( (int) $row['id'] );
            if ( $job )
                $out[] = $job;
        }
        return $out;
    }

    // ---- audit --------------------------------------------------------------------------------------------------

    /**
     * Records an operator action in the audit trail (system.queue.<action>), when the kernel has one.
     *
     * @param string $action job.cancel, job.retry, job.dead, group.cancel, pause, resume, purge
     * @param array $data
     */
    public static function audit( $action, array $data )
    {
        if ( !self::$auditEnabled || !class_exists( 'expAuditHook' ) )
            return;
        try
        {
            expAuditHook::emit( 'system.queue.' . $action, array( 'object' => array( 'type' => 'queue' ) + $data, 'after' => $data ) );
        }
        catch ( Throwable $e )
        {
        }
    }
}
