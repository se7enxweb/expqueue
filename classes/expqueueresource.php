<?php
/**
 * File containing the expQueueResource class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The concurrency limiter of a resource (table expqueue_resource): a key such as "anthropic:standard" or "imaging"
 * that jobs name with the option resource. At most max_concurrent jobs of a resource run at once across all workers.
 *
 * The limit adapts when [QueueResource_<key>] (or [ResourceSettings]) has Adaptive=enabled, from what handlers report
 * with $context->rateLimit() (the rate-limit headers of an API):
 *   - a 429/529, or a retry-after: the current limit is halved (not below MinConcurrent) and the resource cools down
 *     for retry-after seconds (DefaultCooldown when none is given): no new job of it starts meanwhile;
 *   - remaining requests at or below LowWater: the limit drops to MinConcurrent; at 0 it cools down until reset_at;
 *   - 401/403: the resource is paused (a key or billing problem needs a person) and stays paused until resumed;
 *   - IncreaseAfter successes in a row: the limit grows by one, up to MaxConcurrent (additive increase).
 */
class expQueueResource
{
    /** @var array key => row, the rows read in this dispatch round */
    protected static $cache = array();

    /** Forgets the rows read in this round. */
    public static function clearCache()
    {
        self::$cache = array();
    }

    /** Creates the row of a resource when it is missing. */
    public static function ensure( $key )
    {
        $db = expQueue::db();
        $k = $db->escapeString( (string) $key );
        $rows = $db->arrayQuery( "SELECT resource_key FROM expqueue_resource WHERE resource_key = '$k'" );
        if ( !is_array( $rows ) || !$rows )
        {
            $max = expQueueConfig::instance()->resource( $key )['max_concurrent'];
            $db->query( "INSERT INTO expqueue_resource ( resource_key, paused, max_concurrent, cooldown_until, remaining_requests, remaining_tokens, reset_at, success_streak, last_status, modified )"
                        . " VALUES ( '$k', 0, $max, 0, -1, -1, 0, 0, '', " . expQueue::now() . ' )' );
        }
        unset( self::$cache[$key] );
    }

    /**
     * The state of a resource with its settings.
     *
     * @return array key, paused, limit (the current, adapted limit), max (the configured ceiling), cooldown_until,
     *               remaining_requests, remaining_tokens, reset_at, success_streak, last_status, modified
     */
    public static function state( $key )
    {
        if ( isset( self::$cache[$key] ) )
            return self::$cache[$key];
        $config = expQueueConfig::instance()->resource( $key );
        $db = expQueue::db();
        $rows = $db->arrayQuery( "SELECT * FROM expqueue_resource WHERE resource_key = '" . $db->escapeString( (string) $key ) . "'" );
        $row = is_array( $rows ) && isset( $rows[0] ) ? array_change_key_case( $rows[0], CASE_LOWER ) : array();
        $limit = isset( $row['max_concurrent'] ) && (int) $row['max_concurrent'] > 0 ? (int) $row['max_concurrent'] : $config['max_concurrent'];
        $limit = $config['adaptive'] ? min( $config['max_concurrent'], max( $config['min_concurrent'], $limit ) ) : $config['max_concurrent'];
        return self::$cache[$key] = array(
            'key' => (string) $key,
            'paused' => isset( $row['paused'] ) ? (int) $row['paused'] : 0,
            'limit' => $limit,
            'max' => $config['max_concurrent'],
            'min' => $config['min_concurrent'],
            'adaptive' => $config['adaptive'],
            'cooldown_until' => isset( $row['cooldown_until'] ) ? (int) $row['cooldown_until'] : 0,
            'remaining_requests' => isset( $row['remaining_requests'] ) ? (int) $row['remaining_requests'] : -1,
            'remaining_tokens' => isset( $row['remaining_tokens'] ) ? (int) $row['remaining_tokens'] : -1,
            'reset_at' => isset( $row['reset_at'] ) ? (int) $row['reset_at'] : 0,
            'success_streak' => isset( $row['success_streak'] ) ? (int) $row['success_streak'] : 0,
            'last_status' => isset( $row['last_status'] ) ? (string) $row['last_status'] : '',
            'modified' => isset( $row['modified'] ) ? (int) $row['modified'] : 0,
            'known' => (bool) $row,
        );
    }

    /**
     * Whether a job of the resource may start now.
     *
     * @param string $key
     * @param int $running jobs of the resource that run now
     * @return string|null null when it may, else why not (paused, cooldown, full)
     */
    public static function blocked( $key, $running )
    {
        if ( $key === '' )
            return null;
        $s = self::state( $key );
        if ( $s['paused'] )
            return 'paused';
        if ( $s['cooldown_until'] > expQueue::now() )
            return 'cooldown';
        if ( $running >= $s['limit'] )
            return 'full';
        return null;
    }

    /** @return array[] the state of every resource that has a row, a setting or running/waiting jobs */
    public static function all()
    {
        $keys = expQueueConfig::instance()->configuredResources();
        $rows = expQueue::db()->arrayQuery( 'SELECT resource_key FROM expqueue_resource' );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $keys[] = (string) $row['resource_key'];
        }
        foreach ( array_keys( expQueue::countBy( 'resource_key', array( 'state' => expQueueJob::activeStates() ) ) ) as $key )
            $keys[] = (string) $key;
        $out = array();
        foreach ( array_unique( $keys ) as $key )
            if ( $key !== '' )
                $out[$key] = self::state( $key );
        ksort( $out );
        return $out;
    }

    /**
     * Takes in what a handler learnt about a resource's rate limits.
     *
     * @param string $key
     * @param array $hints status (HTTP status), retry_after (seconds), remaining_requests, remaining_tokens,
     *                     reset_at (timestamp) or reset_in (seconds)
     */
    public static function observe( $key, array $hints )
    {
        if ( $key === '' )
            return;
        self::ensure( $key );
        $s = self::state( $key );
        $config = expQueueConfig::instance()->resource( $key );
        $now = expQueue::now();
        $set = array( 'modified' => $now );
        $status = isset( $hints['status'] ) ? (int) $hints['status'] : 0;
        $retryAfter = isset( $hints['retry_after'] ) ? max( 0, (int) $hints['retry_after'] ) : 0;
        $resetAt = isset( $hints['reset_at'] ) ? (int) $hints['reset_at'] : ( isset( $hints['reset_in'] ) ? $now + max( 0, (int) $hints['reset_in'] ) : 0 );
        if ( isset( $hints['remaining_requests'] ) )
            $set['remaining_requests'] = (int) $hints['remaining_requests'];
        if ( isset( $hints['remaining_tokens'] ) )
            $set['remaining_tokens'] = (int) $hints['remaining_tokens'];
        if ( $resetAt )
            $set['reset_at'] = $resetAt;
        if ( $status )
            $set['last_status'] = (string) $status;

        if ( $status === 401 || $status === 403 )
        {
            $set['paused'] = 1;
            $set['last_status'] = $status . ' (paused: check the credentials)';
            expQueue::audit( 'pause', array( 'scope' => 'resource', 'name' => $key, 'reason' => "HTTP $status" ) );
        }
        elseif ( $status === 429 || $status === 529 || $retryAfter > 0 )
        {
            $cool = $retryAfter > 0 ? $retryAfter : ( $resetAt > $now ? $resetAt - $now : $config['default_cooldown'] );
            $set['cooldown_until'] = max( $s['cooldown_until'], $now + $cool );
            $set['success_streak'] = 0;
            if ( $config['adaptive'] )
                $set['max_concurrent'] = max( $config['min_concurrent'], (int) floor( $s['limit'] / 2 ) );
        }
        elseif ( isset( $hints['remaining_requests'] ) && (int) $hints['remaining_requests'] >= 0 && (int) $hints['remaining_requests'] <= $config['low_water'] )
        {
            if ( $config['adaptive'] )
                $set['max_concurrent'] = $config['min_concurrent'];
            $set['success_streak'] = 0;
            if ( (int) $hints['remaining_requests'] === 0 && $resetAt > $now )
                $set['cooldown_until'] = $resetAt;
        }
        self::write( $key, $set );
    }

    /** One job of the resource ended well: the additive increase of the limit. */
    public static function success( $key )
    {
        if ( $key === '' )
            return;
        $config = expQueueConfig::instance()->resource( $key );
        if ( !$config['adaptive'] )
            return;
        $s = self::state( $key );
        if ( !$s['known'] )
            return;
        $streak = $s['success_streak'] + 1;
        $set = array( 'success_streak' => $streak, 'modified' => expQueue::now() );
        if ( $streak >= $config['increase_after'] && $s['limit'] < $config['max_concurrent'] )
        {
            $set['max_concurrent'] = $s['limit'] + 1;
            $set['success_streak'] = 0;
        }
        self::write( $key, $set );
    }

    /** Sets the limit of a resource by hand (CLI, tests); 0 = back to the configured MaxConcurrent. */
    public static function setLimit( $key, $limit )
    {
        self::ensure( $key );
        $limit = (int) $limit > 0 ? (int) $limit : expQueueConfig::instance()->resource( $key )['max_concurrent'];
        self::write( $key, array( 'max_concurrent' => $limit, 'cooldown_until' => 0, 'success_streak' => 0, 'modified' => expQueue::now() ) );
    }

    protected static function write( $key, array $set )
    {
        $parts = array();
        foreach ( $set as $field => $value )
            $parts[] = $field . ' = ' . expQueue::sqlValue( $value );
        $db = expQueue::db();
        $db->query( 'UPDATE expqueue_resource SET ' . implode( ', ', $parts ) . " WHERE resource_key = '" . $db->escapeString( (string) $key ) . "'" );
        unset( self::$cache[$key] );
    }
}
