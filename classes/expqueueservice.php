<?php
/**
 * File containing the expQueueService class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The systemd service of the queue's workers: ./console ext:expqueue:service install|start|stop|status|uninstall.
 *
 * install writes the unit template <UnitName>@.service (from share/systemd/expqueue-worker@.service.in) into
 * [ServiceSettings] UnitDirectory, for the site user (the owner of var/, as the workers use when they drop
 * privileges), reloads systemd and enables and starts the instances @1..@Instances. Everything but status and a
 * --dry-run needs root. The cron part keeps running as the safety net; it skips its worker slice while a worker's
 * heartbeat is fresh.
 *
 * What the service did last is kept in var/<vardir>/jobs/expqueue/service.json, which the dashboard reads (a web
 * request cannot ask systemd).
 */
class expQueueService
{
    /** @return string the installation's directory */
    public static function root()
    {
        return rtrim( eZSys::rootDir(), '/' );
    }

    /** @return string the unit name without @ and suffix (expqueue-<installation directory>) */
    public static function unitName()
    {
        $name = trim( (string) expQueueConfig::instance()->service( 'UnitName', 'auto' ) );
        if ( $name === '' || $name === 'auto' )
            $name = 'expqueue-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( basename( self::root() ) ) ), '-' );
        return substr( preg_replace( '/[^A-Za-z0-9_.-]/', '', $name ), 0, 80 );
    }

    /** @return int the number of worker instances */
    public static function instances()
    {
        return max( 1, min( 16, (int) expQueueConfig::instance()->service( 'Instances', 1 ) ) );
    }

    /** @return string the unit file's path */
    public static function unitPath()
    {
        $dir = rtrim( (string) expQueueConfig::instance()->service( 'UnitDirectory', '/etc/systemd/system' ), '/' );
        return $dir . '/' . self::unitName() . '@.service';
    }

    /** @return string[] the instance names (<name>@1.service ...) */
    public static function instanceUnits( $count = null )
    {
        $out = array();
        for ( $i = 1; $i <= ( $count ?: self::instances() ); $i++ )
            $out[] = self::unitName() . '@' . $i . '.service';
        return $out;
    }

    /** @return array user, group, uid, gid of the site user; user is '' when the installation belongs to root */
    public static function siteUser()
    {
        list( $uid, $gid ) = expQueueLock::owner();
        $pw = $uid && function_exists( 'posix_getpwuid' ) ? posix_getpwuid( $uid ) : false;
        $gr = $gid && function_exists( 'posix_getgrgid' ) ? posix_getgrgid( $gid ) : false;
        return array( 'user' => $pw ? $pw['name'] : ( $uid ? (string) $uid : '' ), 'group' => $gr ? $gr['name'] : ( $gid ? (string) $gid : '' ),
                      'uid' => $uid, 'gid' => $gid );
    }

    /** @return string the PHP command line binary for the unit */
    public static function phpBinary()
    {
        $php = trim( (string) expQueueConfig::instance()->service( 'PhpBinary', '' ) );
        if ( $php !== '' && is_executable( $php ) )
            return $php;
        if ( class_exists( 'expProcessTools' ) && method_exists( 'expProcessTools', 'phpCli' ) )
        {
            $found = expProcessTools::phpCli();
            if ( $found )
                return $found;
        }
        return PHP_BINARY;
    }

    /**
     * The unit file's text.
     *
     * @param string $siteaccess '' = the default siteaccess
     * @return string
     */
    public static function render( $siteaccess = '' )
    {
        $template = eZExtension::baseDirectory() . '/expqueue/share/systemd/expqueue-worker@.service.in';
        $text = is_file( $template ) ? file_get_contents( $template ) : '';
        if ( $text === '' )
            throw new expQueueException( "the unit template $template is missing" );
        $config = expQueueConfig::instance();
        $user = self::siteUser();
        $timeout = $config->queueInt( 'DefaultTimeout', 1 ) + $config->queueInt( 'LeaseMargin' );
        $map = array(
            '@ROOT@' => self::root(),
            '@USER@' => $user['user'],
            '@GROUP@' => $user['group'] !== '' ? $user['group'] : $user['user'],
            '@PHP@' => self::phpBinary(),
            '@NAME@' => self::unitName(),
            '@MAXSECONDS@' => (string) max( 60, (int) $config->worker( 'MaxSeconds', 3600 ) ),
            '@MAXJOBS@' => (string) max( 1, (int) $config->worker( 'MaxJobs', 500 ) ),
            '@SITEACCESS@' => $siteaccess !== '' ? ' -s ' . preg_replace( '/[^A-Za-z0-9_-]/', '', $siteaccess ) : '',
            '@NICE@' => (string) max( -20, min( 19, (int) $config->service( 'Nice', 10 ) ) ),
            '@CPUWEIGHT@' => (string) max( 1, min( 10000, (int) $config->service( 'CPUWeight', 30 ) ) ),
            '@MEMORYMAX@' => preg_replace( '/[^0-9KMGTkmgt%]/', '', (string) $config->service( 'MemoryMax', '512M' ) ) ?: '512M',
            '@TASKSMAX@' => (string) max( 8, (int) $config->service( 'TasksMax', 64 ) ),
            '@STOPSECONDS@' => (string) max( 30, $timeout ),
        );
        return strtr( $text, $map );
    }

    /**
     * The steps of an action, as the commands they run (shown by --dry-run, run by perform()).
     *
     * @param string $action install, start, stop, status, uninstall
     * @return array list of array( 'write' => path, 'text' => ... ) or array( 'run' => argv )
     */
    public static function plan( $action, $siteaccess = '' )
    {
        $units = self::instanceUnits();
        $systemctl = self::systemctl();
        switch ( $action )
        {
            case 'install':
                $steps = array( array( 'write' => self::unitPath(), 'text' => self::render( $siteaccess ) ),
                                array( 'run' => array( $systemctl, 'daemon-reload' ) ),
                                array( 'run' => array_merge( array( $systemctl, 'enable', '--now' ), $units ) ) );
                return $steps;
            case 'start':
                return array( array( 'run' => array_merge( array( $systemctl, 'start' ), $units ) ) );
            case 'stop':
                return array( array( 'run' => array_merge( array( $systemctl, 'stop' ), $units ) ) );
            case 'status':
                return array( array( 'run' => array_merge( array( $systemctl, 'is-active' ), $units ) ) );
            case 'uninstall':
                return array( array( 'run' => array_merge( array( $systemctl, 'disable', '--now' ), $units ) ),
                              array( 'remove' => self::unitPath() ),
                              array( 'run' => array( $systemctl, 'daemon-reload' ) ) );
        }
        throw new expQueueException( "unknown action $action: install, start, stop, status or uninstall" );
    }

    /** @return string the systemctl binary */
    public static function systemctl()
    {
        foreach ( array( '/usr/bin/systemctl', '/bin/systemctl' ) as $path )
            if ( @is_executable( $path ) )
                return $path;
        return 'systemctl';
    }

    /**
     * Runs the steps of an action.
     *
     * @param string $action
     * @param callable $say receives a line
     * @return int exit code
     */
    public static function perform( $action, $siteaccess, $say )
    {
        $isRoot = function_exists( 'posix_geteuid' ) && posix_geteuid() === 0;
        if ( $action !== 'status' && !$isRoot )
        {
            call_user_func( $say, 'FAIL ' . $action . ' needs root (it writes ' . self::unitPath() . ' and runs systemctl); try --dry-run to see what it does' );
            return 1;
        }
        if ( $action === 'install' && self::siteUser()['user'] === '' )
        {
            call_user_func( $say, 'FAIL the installation belongs to root: there is no site user to run the worker as' );
            return 1;
        }
        $code = 0;
        foreach ( self::plan( $action, $siteaccess ) as $step )
        {
            if ( isset( $step['write'] ) )
            {
                if ( @file_put_contents( $step['write'], $step['text'] ) === false )
                {
                    call_user_func( $say, 'FAIL could not write ' . $step['write'] );
                    return 1;
                }
                @chmod( $step['write'], 0644 );
                call_user_func( $say, 'wrote ' . $step['write'] );
            }
            elseif ( isset( $step['remove'] ) )
            {
                if ( is_file( $step['remove'] ) && !@unlink( $step['remove'] ) )
                {
                    call_user_func( $say, 'FAIL could not remove ' . $step['remove'] );
                    return 1;
                }
                call_user_func( $say, 'removed ' . $step['remove'] );
            }
            else
            {
                list( $rc, $out ) = self::exec( $step['run'] );
                call_user_func( $say, '$ ' . implode( ' ', $step['run'] ) . ( $out !== '' ? "\n" . rtrim( $out ) : '' ) );
                if ( $rc !== 0 )
                    $code = $action === 'status' ? 3 : 1;
                if ( $rc !== 0 && $action !== 'status' )
                    break;
            }
        }
        if ( $action !== 'status' )
            self::remember( $action, $code === 0 );
        return $code;
    }

    /** @return array exit code, output */
    protected static function exec( array $argv )
    {
        $pipes = array();
        $proc = @proc_open( $argv, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
        if ( !is_resource( $proc ) )
            return array( 127, 'could not run ' . $argv[0] );
        $out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        return array( proc_close( $proc ), (string) $out );
    }

    /** Keeps what the service did last in service.json (read by the dashboard). */
    protected static function remember( $action, $ok )
    {
        $file = expQueueLock::directory() . '/service.json';
        $data = array( 'unit' => self::unitName(), 'instances' => self::instances(), 'user' => self::siteUser()['user'],
                       'action' => $action, 'ok' => $ok, 'at' => time(),
                       'installed' => in_array( $action, array( 'install', 'start', 'stop' ), true ) ? true : ( $action === 'uninstall' ? false : null ) );
        $old = self::stored();
        if ( $data['installed'] === null )
            $data['installed'] = !empty( $old['installed'] );
        if ( $action === 'stop' )
            $data['running'] = false;
        elseif ( in_array( $action, array( 'install', 'start' ), true ) )
            $data['running'] = $ok;
        else
            $data['running'] = false;
        @file_put_contents( $file, json_encode( $data, JSON_PRETTY_PRINT ) . "\n" );
        expQueueLock::fixOwner( $file );
    }

    /** @return array what service.json says (empty when the service was never installed) */
    public static function stored()
    {
        try
        {
            $file = expQueueLock::directory() . '/service.json';
        }
        catch ( Throwable $e )
        {
            return array();
        }
        $data = is_file( $file ) ? json_decode( (string) @file_get_contents( $file ), true ) : null;
        return is_array( $data ) ? $data : array();
    }

    /**
     * The service at a glance, without asking systemd (works in a web request).
     *
     * @return array unit, installed, workers (live workers with origin systemd), last_action, at
     */
    public static function statusSummary()
    {
        $stored = self::stored();
        return array(
            'unit' => isset( $stored['unit'] ) ? $stored['unit'] : self::unitName(),
            'installed' => !empty( $stored['installed'] ),
            'workers' => count( expQueueWorker::live( 'systemd' ) ),
            'last_action' => isset( $stored['action'] ) ? $stored['action'] : '',
            'at' => isset( $stored['at'] ) ? (int) $stored['at'] : 0,
        );
    }
}
