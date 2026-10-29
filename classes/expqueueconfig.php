<?php
/**
 * File containing the expQueueConfig class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The settings of expqueue (expqueue.ini), with the defaults that apply when a setting is missing. Every value is
 * read through here, so a missing or wrong setting never breaks a worker: it falls back to the documented default
 * (doc/configuration.md lists them all).
 */
class expQueueConfig
{
    /** The defaults of [QueueSettings] */
    const QUEUE_DEFAULTS = array(
        'Paused' => 'disabled',
        'DefaultClass' => 'bulk',
        'StarvationSeconds' => 1800,
        'AgingStep' => 120,
        'AgingMax' => 40,
        'MinShareWindow' => 600,
        'CandidateLimit' => 50,
        'DefaultTimeout' => 300,
        'DefaultMaxAttempts' => 5,
        'LeaseMargin' => 60,
        'RetryBase' => 30,
        'MaxBackoff' => 3600,
        'IdempotencyWindow' => 86400,
        'KeepDays' => 30,
        'KeepDeadDays' => 90,
        'DatabaseMutex' => 'auto',
        'DispatchLockSeconds' => 30,
        'MaintenanceInterval' => 5,
        'MaxEventsPerJob' => 200,
    );

    /** The classes when [QueueSettings] Classes[] is empty: name => weight */
    const DEFAULT_CLASSES = array(
        'interactive' => 50, 'editorial' => 30, 'chain' => 15, 'translation_patch' => 10, 'translation_full' => 5,
        'bulk' => 4, 'system' => 10,
    );

    /** @var expQueueConfig|null */
    protected static $instance = null;

    /** @var eZINI */
    protected $ini;

    /** @var array|null cache of classes() */
    protected $classes = null;

    /** @var array|null cache of jobTypes() */
    protected $types = null;

    protected function __construct()
    {
        $this->ini = eZINI::instance( 'expqueue.ini' );
    }

    /** @return expQueueConfig */
    public static function instance()
    {
        if ( self::$instance === null )
            self::$instance = new self();
        return self::$instance;
    }

    /** Forgets the cached settings (tests; a long-running worker after an INI change). */
    public static function reset()
    {
        self::$instance = null;
    }

    /** @return mixed a setting of [QueueSettings] or its default */
    public function queue( $name )
    {
        $default = isset( self::QUEUE_DEFAULTS[$name] ) ? self::QUEUE_DEFAULTS[$name] : null;
        return $this->value( 'QueueSettings', $name, $default );
    }

    /** @return int an integer setting of [QueueSettings], at least $min */
    public function queueInt( $name, $min = 0 )
    {
        return max( $min, (int) $this->queue( $name ) );
    }

    /** @return mixed a setting of any group, or $default when the group or the setting is missing */
    public function value( $group, $name, $default = null )
    {
        if ( $this->ini->hasVariable( $group, $name ) )
        {
            $value = $this->ini->variable( $group, $name );
            if ( $value !== '' && $value !== null && $value !== array() )
                return $value;
        }
        return $default;
    }

    /** @return bool whether a setting of the form enabled/disabled (or true/false) is on */
    public function enabled( $group, $name, $default = false )
    {
        $value = $this->value( $group, $name, null );
        if ( $value === null )
            return $default;
        return in_array( strtolower( trim( (string) $value ) ), array( 'enabled', 'true', 'yes', 'on', '1' ), true );
    }

    /**
     * The priority classes in the configured order.
     *
     * @return array name => array( weight, max_running, min_share, reserved, default_priority, max_attempts )
     */
    public function classes()
    {
        if ( $this->classes !== null )
            return $this->classes;
        $names = (array) $this->value( 'QueueSettings', 'Classes', array() );
        $names = array_values( array_filter( array_map( 'trim', $names ), 'strlen' ) );
        if ( !$names )
            $names = array_keys( self::DEFAULT_CLASSES );
        $out = array();
        foreach ( $names as $name )
        {
            if ( !preg_match( '/^[a-z0-9_]{1,40}$/', $name ) )
                continue;
            $group = 'QueueClass_' . $name;
            $weight = (float) $this->value( $group, 'Weight', isset( self::DEFAULT_CLASSES[$name] ) ? self::DEFAULT_CLASSES[$name] : 5 );
            $out[$name] = array(
                'name' => $name,
                'weight' => max( 0.01, $weight ),
                'max_running' => max( 0, (int) $this->value( $group, 'MaxRunning', 0 ) ),
                'min_share' => min( 1.0, max( 0.0, (float) $this->value( $group, 'MinShare', 0 ) ) ),
                'reserved' => $this->enabled( $group, 'Reserved', $name === 'system' ),
                'default_priority' => min( 100, max( 0, (int) $this->value( $group, 'DefaultPriority', 50 ) ) ),
                'max_attempts' => max( 0, (int) $this->value( $group, 'MaxAttempts', 0 ) ),
            );
        }
        return $this->classes = $out;
    }

    /** @return bool */
    public function hasClass( $name )
    {
        $classes = $this->classes();
        return isset( $classes[$name] );
    }

    /** @return string the class of jobs that name none */
    public function defaultClass()
    {
        $class = (string) $this->queue( 'DefaultClass' );
        if ( $this->hasClass( $class ) )
            return $class;
        $names = array_keys( $this->classes() );
        return $names ? end( $names ) : 'bulk';
    }

    /**
     * The job types: every group [JobType_<type>] with a Handler.
     *
     * @return array type => array( handler, class, priority, max_attempts, timeout, resource, retry_base, max_backoff )
     */
    public function jobTypes()
    {
        if ( $this->types !== null )
            return $this->types;
        $out = array();
        foreach ( array_keys( (array) $this->ini->groups() ) as $group )
        {
            if ( strpos( $group, 'JobType_' ) !== 0 )
                continue;
            $type = substr( $group, 8 );
            $handler = trim( (string) $this->value( $group, 'Handler', '' ) );
            if ( $type === '' || $handler === '' )
                continue;
            $out[$type] = array(
                'type' => $type,
                'handler' => $handler,
                'class' => (string) $this->value( $group, 'Class', '' ),
                'priority' => $this->value( $group, 'Priority', null ),
                'max_attempts' => (int) $this->value( $group, 'MaxAttempts', 0 ),
                'timeout' => (int) $this->value( $group, 'Timeout', 0 ),
                'resource' => (string) $this->value( $group, 'Resource', '' ),
                'retry_base' => (int) $this->value( $group, 'RetryBase', 0 ),
                'max_backoff' => (int) $this->value( $group, 'MaxBackoff', 0 ),
                'description' => (string) $this->value( $group, 'Description', '' ),
            );
        }
        ksort( $out );
        return $this->types = $out;
    }

    /** @return array|null the settings of one job type */
    public function jobType( $type )
    {
        $types = $this->jobTypes();
        return isset( $types[$type] ) ? $types[$type] : null;
    }

    /**
     * The limits of a resource: [QueueResource_<key>], else the [ResourceSettings] defaults.
     *
     * @return array max_concurrent, min_concurrent, adaptive, increase_after, low_water, default_cooldown
     */
    public function resource( $key )
    {
        $group = 'QueueResource_' . $key;
        $default = function ( $name, $value ) use ( $group )
        {
            return $this->value( $group, $name, $this->value( 'ResourceSettings', $name, $value ) );
        };
        $max = max( 1, (int) $default( 'MaxConcurrent', 4 ) );
        return array(
            'max_concurrent' => $max,
            'min_concurrent' => min( $max, max( 1, (int) $default( 'MinConcurrent', 1 ) ) ),
            'adaptive' => in_array( strtolower( (string) $default( 'Adaptive', 'enabled' ) ), array( 'enabled', 'true', '1' ), true ),
            'increase_after' => max( 1, (int) $default( 'IncreaseAfter', 10 ) ),
            'low_water' => max( 0, (int) $default( 'LowWater', 2 ) ),
            'default_cooldown' => max( 1, (int) $default( 'DefaultCooldown', 30 ) ),
        );
    }

    /** @return string[] the resources named in the settings ([QueueResource_<key>] groups) */
    public function configuredResources()
    {
        $out = array();
        foreach ( array_keys( (array) $this->ini->groups() ) as $group )
            if ( strpos( $group, 'QueueResource_' ) === 0 )
                $out[] = substr( $group, 14 );
        return $out;
    }

    /** @return array the quotas of [QueueQuotas] (0 = none) */
    public function quotas()
    {
        $out = array();
        foreach ( array( 'MaxQueuedPerUser', 'MaxRunningPerUser', 'MaxQueuedPerSite', 'MaxRunningPerSite' ) as $name )
            $out[$name] = max( 0, (int) $this->value( 'QueueQuotas', $name, 0 ) );
        return $out;
    }

    /**
     * The rules of [QueueRules] Rules[], in order: each matches on Type (a pattern with *), Resource, Site and Tag,
     * and sets Class, Priority, Resource and MaxAttempts.
     *
     * @return array
     */
    public function rules()
    {
        $out = array();
        foreach ( (array) $this->value( 'QueueRules', 'Rules', array() ) as $name )
        {
            $group = 'QueueRule_' . trim( (string) $name );
            if ( !$this->ini->hasGroup( $group ) )
                continue;
            $out[] = array(
                'name' => (string) $name,
                'type' => (string) $this->value( $group, 'Type', '' ),
                'resource' => (string) $this->value( $group, 'MatchResource', '' ),
                'site' => (string) $this->value( $group, 'Site', '' ),
                'tag' => (string) $this->value( $group, 'Tag', '' ),
                'set_class' => (string) $this->value( $group, 'Class', '' ),
                'set_priority' => $this->value( $group, 'Priority', null ),
                'set_resource' => (string) $this->value( $group, 'Resource', '' ),
                'set_max_attempts' => (int) $this->value( $group, 'MaxAttempts', 0 ),
            );
        }
        return $out;
    }

    /** @return mixed a setting of [WorkerSettings] */
    public function worker( $name, $default = null )
    {
        return $this->value( 'WorkerSettings', $name, $default );
    }

    /** @return mixed a setting of [ServiceSettings] */
    public function service( $name, $default = null )
    {
        return $this->value( 'ServiceSettings', $name, $default );
    }

    /** @return int a byte count from a PHP-style size (256M, 1G, 524288) */
    public static function bytes( $value )
    {
        $value = trim( (string) $value );
        if ( !preg_match( '/^(\d+)\s*([kmg]?)b?$/i', $value, $m ) )
            return 0;
        $n = (int) $m[1];
        switch ( strtolower( $m[2] ) )
        {
            case 'g': return $n * 1073741824;
            case 'm': return $n * 1048576;
            case 'k': return $n * 1024;
        }
        return $n;
    }
}
