<?php
/**
 * File containing the expQueueLock class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The dispatcher lock and the queue's directory.
 *
 * The lock serialises the short decisions of the queue (enqueue with its idempotency and quota checks, a dispatch
 * round, the maintenance), never the work itself. It has two parts:
 *   - a local flock on var/<vardir>/jobs/expqueue/<name>.lock: every process on this host (Apache/FPM as the site
 *     user, Velocity as root, CLI workers) waits for the same file;
 *   - with [QueueSettings] DatabaseMutex=enabled (auto: every engine but SQLite, which is single-host by nature) a row
 *     of expqueue_mutex taken by compare-and-set with an expiry (DispatchLockSeconds), for clusters of several hosts.
 * It is reentrant within a process: expQueue::withLock() around a loop of enqueue() calls takes it once.
 *
 * The directory is 0770 with a deny-all .htaccess, and every file in it belongs to the site user even when root
 * creates it (the owner of var/, as expContentJobStore does), so a root-started Velocity worker and the FPM pool of
 * the site user can always both open the lock.
 */
class expQueueLock
{
    /** @var string|null a directory of its own (tests, sandboxes); null = var/<vardir>/jobs/expqueue */
    protected static $directory = null;

    /** @var array|null uid, gid of the site user */
    protected static $owner = null;

    /** @var array name => array( handle, depth, db ) */
    protected static $held = array();

    /** @var string|null this process's lock owner id */
    protected static $me = null;

    /** Uses another directory (tests, sandboxes); null goes back to the default. */
    public static function setDirectory( $directory )
    {
        self::$directory = $directory === null ? null : rtrim( (string) $directory, '/' );
        self::$owner = null;
    }

    /** @return string the queue's directory, created when it is missing */
    public static function directory()
    {
        $dir = self::$directory;
        if ( $dir === null )
        {
            $varDir = eZSys::varDirectory();
            if ( $varDir === '' || $varDir === null )
                $varDir = 'var';
            if ( $varDir[0] !== '/' )
                $varDir = eZSys::rootDir() . '/' . $varDir;
            $dir = $varDir . '/jobs/expqueue';
        }
        if ( !is_dir( $dir ) )
        {
            $old = umask( eZFile::creationUmask( 0007 ) );
            @mkdir( $dir, eZDir::dirMode( 0770 ), true );
            umask( $old );
            if ( !is_dir( $dir ) )
                throw new expQueueException( "The queue directory $dir cannot be created." );
            self::fixOwner( dirname( $dir ) );
            self::fixOwner( $dir );
        }
        if ( !is_file( "$dir/.htaccess" ) )
        {
            @file_put_contents( "$dir/.htaccess", "Require all denied\n" );
            self::fixOwner( "$dir/.htaccess" );
        }
        return $dir;
    }

    /** @return string this process's id as a lock and lease owner: host:pid:random */
    public static function me()
    {
        if ( self::$me === null || strpos( self::$me, ':' . getmypid() . ':' ) === false )
            self::$me = substr( preg_replace( '/[^A-Za-z0-9.-]/', '', php_uname( 'n' ) ), 0, 40 ) . ':' . getmypid() . ':' . bin2hex( random_bytes( 4 ) );
        return self::$me;
    }

    /**
     * Takes a lock (reentrant).
     *
     * @param string $name
     * @param float $wait seconds to wait at most
     * @return bool
     */
    public static function acquire( $name = 'dispatch', $wait = 10.0 )
    {
        $name = preg_replace( '/[^a-z0-9_.-]/', '', strtolower( (string) $name ) );
        if ( isset( self::$held[$name] ) )
        {
            self::$held[$name]['depth']++;
            return true;
        }
        $file = self::directory() . "/$name.lock";
        $existed = is_file( $file );
        $fh = @fopen( $file, 'c' );
        if ( !$fh )
            return false;
        if ( !$existed )
        {
            @chmod( $file, eZFile::fileMode( 0660 ) );
            self::fixOwner( $file );
        }
        $until = microtime( true ) + max( 0.0, (float) $wait );
        $sleep = 2000;
        while ( !flock( $fh, LOCK_EX | LOCK_NB ) )
        {
            if ( microtime( true ) >= $until )
            {
                fclose( $fh );
                return false;
            }
            usleep( $sleep );
            $sleep = min( 50000, $sleep * 2 );
        }
        $db = false;
        if ( self::useDatabaseMutex() )
        {
            if ( !self::takeMutex( $name, $until ) )
            {
                flock( $fh, LOCK_UN );
                fclose( $fh );
                return false;
            }
            $db = true;
        }
        self::$held[$name] = array( 'handle' => $fh, 'depth' => 1, 'db' => $db );
        return true;
    }

    /** Gives a lock back (the outermost release frees it). */
    public static function release( $name = 'dispatch' )
    {
        $name = preg_replace( '/[^a-z0-9_.-]/', '', strtolower( (string) $name ) );
        if ( !isset( self::$held[$name] ) )
            return;
        if ( --self::$held[$name]['depth'] > 0 )
            return;
        $lock = self::$held[$name];
        unset( self::$held[$name] );
        if ( $lock['db'] )
            self::releaseMutex( $name );
        flock( $lock['handle'], LOCK_UN );
        fclose( $lock['handle'] );
    }

    /** @return bool whether this process holds the lock */
    public static function holds( $name = 'dispatch' )
    {
        return isset( self::$held[$name] );
    }

    /** Frees every lock (a forked child, a test). */
    public static function forget()
    {
        foreach ( self::$held as $lock )
            @fclose( $lock['handle'] );
        self::$held = array();
        self::$me = null;
    }

    /** @return bool */
    public static function useDatabaseMutex()
    {
        $setting = strtolower( (string) expQueueConfig::instance()->queue( 'DatabaseMutex' ) );
        if ( $setting === 'enabled' )
            return true;
        if ( $setting === 'disabled' )
            return false;
        return expQueueSchema::engineOf( eZDB::instance() ) !== 'sqlite';
    }

    /**
     * Takes the row of expqueue_mutex by compare-and-set: free or expired rows are taken, then the owner is read
     * back (eZDB reports no affected rows, so the read decides who won).
     */
    protected static function takeMutex( $name, $until )
    {
        $db = eZDB::instance();
        $me = $db->escapeString( self::me() );
        $n = $db->escapeString( $name );
        $seconds = expQueueConfig::instance()->queueInt( 'DispatchLockSeconds', 5 );
        $rows = $db->arrayQuery( "SELECT name FROM expqueue_mutex WHERE name = '$n'" );
        if ( !is_array( $rows ) || !$rows )
            $db->query( "INSERT INTO expqueue_mutex ( name, owner, expires, modified ) VALUES ( '$n', '', 0, " . time() . " )" );
        $sleep = 5000;
        while ( true )
        {
            $now = time();
            $expires = $now + $seconds;
            $db->query( "UPDATE expqueue_mutex SET owner = '$me', expires = $expires, modified = $now WHERE name = '$n' AND expires < $now" );
            $db->query( "UPDATE expqueue_mutex SET expires = $expires, modified = $now WHERE name = '$n' AND owner = '$me'" );
            $rows = $db->arrayQuery( "SELECT owner FROM expqueue_mutex WHERE name = '$n'" );
            if ( is_array( $rows ) && isset( $rows[0] ) )
            {
                $row = array_change_key_case( $rows[0], CASE_LOWER );
                if ( (string) $row['owner'] === self::me() )
                    return true;
            }
            if ( microtime( true ) >= $until )
                return false;
            usleep( $sleep );
            $sleep = min( 100000, $sleep * 2 );
        }
    }

    protected static function releaseMutex( $name )
    {
        $db = eZDB::instance();
        $me = $db->escapeString( self::me() );
        $n = $db->escapeString( $name );
        $db->query( "UPDATE expqueue_mutex SET owner = '', expires = 0, modified = " . time() . " WHERE name = '$n' AND owner = '$me'" );
    }

    /** @return array uid, gid of the site user (the owner of var/, else of index.php); 0, 0 when unknown */
    public static function owner()
    {
        if ( self::$owner === null )
        {
            $candidates = array();
            if ( self::$directory !== null )
            {
                for ( $p = self::$directory; $p !== '/' && $p !== '.' && $p !== ''; $p = dirname( $p ) )
                    $candidates[] = $p;
            }
            else
            {
                $varDir = eZSys::varDirectory();
                if ( $varDir && $varDir[0] !== '/' )
                    $varDir = eZSys::rootDir() . '/' . $varDir;
                $candidates[] = $varDir;
                $candidates[] = eZSys::rootDir() . '/var';
                $candidates[] = eZSys::rootDir() . '/index.php';
            }
            self::$owner = array( 0, 0 );
            foreach ( $candidates as $candidate )
            {
                $p = $candidate;
                while ( $p && !file_exists( $p ) && $p !== '/' )
                    $p = dirname( $p );
                if ( $p && file_exists( $p ) && @fileowner( $p ) !== 0 )
                {
                    self::$owner = array( (int) fileowner( $p ), (int) filegroup( $p ) );
                    break;
                }
            }
        }
        return self::$owner;
    }

    /** As root, gives a file or directory to the site user. */
    public static function fixOwner( $path )
    {
        if ( !function_exists( 'posix_geteuid' ) || posix_geteuid() !== 0 || !file_exists( $path ) )
            return;
        list( $uid, $gid ) = self::owner();
        if ( $uid === 0 )
            return;
        if ( @fileowner( $path ) !== $uid )
            @chown( $path, $uid );
        if ( @filegroup( $path ) !== $gid )
            @chgrp( $path, $gid );
    }

    /**
     * As root, becomes the site user before the kernel reads a setting or opens the database (the same rule as the
     * content job worker): the owner of the installation's var/ directory, else of index.php.
     *
     * @param string $root the installation's directory
     * @return string|null an error, or null (done, not root, or nobody to become)
     */
    public static function dropPrivileges( $root )
    {
        if ( class_exists( 'expContentJobWorker' ) && method_exists( 'expContentJobWorker', 'dropPrivileges' ) )
            return expContentJobWorker::dropPrivileges( $root );
        if ( !function_exists( 'posix_geteuid' ) || posix_geteuid() !== 0 )
            return null;
        foreach ( array( "$root/var", "$root/index.php" ) as $candidate )
        {
            if ( file_exists( $candidate ) && fileowner( $candidate ) !== 0 )
            {
                $uid = fileowner( $candidate );
                $gid = filegroup( $candidate );
                $pw = posix_getpwuid( $uid );
                if ( $pw && function_exists( 'posix_initgroups' ) )
                    @posix_initgroups( $pw['name'], $gid );
                if ( !posix_setgid( $gid ) || !posix_setuid( $uid ) )
                    return "could not switch to the installation's owner (uid $uid)";
                putenv( 'HOME=' . ( $pw ? $pw['dir'] : $root ) );
                umask( 0007 );
                return null;
            }
        }
        return null;
    }

    /** @return string the name of the process's user */
    public static function processUser()
    {
        if ( function_exists( 'posix_geteuid' ) )
        {
            $pw = posix_getpwuid( posix_geteuid() );
            return $pw ? $pw['name'] : (string) posix_geteuid();
        }
        return (string) get_current_user();
    }
}
