<?php
/**
 * File containing the expQueueAdmin class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * Helpers of the admin module expqueue: the page result with its path, the rows the templates show (every label and
 * duration prepared here, so the templates only print), the manage check and the safe redirect after an action.
 */
class expQueueAdmin
{
    /** @return array the module result */
    public static function result( $view, $content, array $title )
    {
        $path = array(
            array( 'url' => 'setup/cache', 'text' => ezpI18n::tr( 'design/admin/parts/setup/menu', 'Setup' ) ),
            array( 'url' => 'expqueue/dashboard', 'text' => ezpI18n::tr( 'extension/expqueue', 'Job queue' ) ),
        );
        foreach ( $title as $text )
            $path[] = array( 'url' => false, 'text' => $text );
        return array( 'content' => $content, 'path' => $path );
    }

    /** @return bool whether the current user holds expqueue/manage */
    public static function canManage()
    {
        $user = eZUser::currentUser();
        $access = $user->hasAccessTo( 'expqueue', 'manage' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /** @return string seconds as a short label: 45 s, 12 min, 3 h, 2 d */
    public static function duration( $seconds )
    {
        if ( $seconds === null || $seconds === '' )
            return '–';
        $s = max( 0, (int) $seconds );
        if ( $s < 120 )
            return $s . ' s';
        if ( $s < 7200 )
            return (int) round( $s / 60 ) . ' min';
        if ( $s < 172800 )
            return (int) round( $s / 3600 ) . ' h';
        return (int) round( $s / 86400 ) . ' d';
    }

    /** @return array a job as a row for the templates */
    public static function jobRow( expQueueJob $job )
    {
        $now = expQueue::now();
        $state = (string) $job->attribute( 'state' );
        $created = (int) $job->attribute( 'created' );
        return array(
            'id' => (int) $job->attribute( 'id' ),
            'uuid' => (string) $job->attribute( 'uuid' ),
            'short' => substr( (string) $job->attribute( 'uuid' ), 0, 8 ),
            'type' => (string) $job->attribute( 'type' ),
            'summary' => $job->summary(),
            'class' => (string) $job->attribute( 'class' ),
            'priority' => (int) $job->attribute( 'priority' ),
            'state' => $state,
            'resource' => (string) $job->attribute( 'resource_key' ),
            'attempts' => (int) $job->attribute( 'attempts' ) . '/' . (int) $job->attribute( 'max_attempts' ),
            'age' => self::duration( $now - $created ),
            'created' => $created,
            'started' => (int) $job->attribute( 'started' ),
            'finished' => (int) $job->attribute( 'finished' ),
            'wait' => self::duration( $job->waitSeconds() ),
            'run' => (int) $job->attribute( 'started' ) ? self::duration( $job->runSeconds() ) : '–',
            'progress' => $job->progressPercent(),
            'progress_label' => (int) $job->attribute( 'progress_total' ) > 0
                              ? (int) $job->attribute( 'progress_done' ) . ' / ' . (int) $job->attribute( 'progress_total' ) : '',
            'progress_message' => (string) $job->attribute( 'progress_message' ),
            'error' => (string) $job->attribute( 'last_error' ),
            'error_class' => (string) $job->attribute( 'error_class' ),
            'run_after' => (int) $job->attribute( 'run_after' ),
            'run_after_in' => (int) $job->attribute( 'run_after' ) > $now ? self::duration( (int) $job->attribute( 'run_after' ) - $now ) : '',
            'lease_owner' => (string) $job->attribute( 'lease_owner' ),
            'lease_left' => $state === 'running' ? self::duration( max( 0, $job->leaseLeft() ) ) : '',
            'cancel_requested' => (bool) (int) $job->attribute( 'cancel_requested' ),
            'can_cancel' => $job->canCancel(),
            'can_retry' => $job->canRetry(),
            'group_id' => (int) $job->attribute( 'group_id' ),
            'parent_id' => (int) $job->attribute( 'parent_id' ),
            'user_id' => (int) $job->attribute( 'user_id' ),
            'site' => (string) $job->attribute( 'site' ),
            'idempotency_key' => (string) $job->attribute( 'idempotency_key' ),
            'timeout' => self::duration( (int) $job->attribute( 'timeout' ) ),
            'lane' => (string) $job->attribute( 'lane' ),
            'external_ref' => (string) $job->attribute( 'external_ref' ),
            'cost_estimate' => (int) $job->attribute( 'cost_estimate_micros' ),
            'cost_actual' => (int) $job->attribute( 'cost_actual_micros' ),
        );
    }

    /** @return array a group as a row for the templates */
    public static function groupRow( expQueueGroup $group )
    {
        $p = $group->progress();
        return array(
            'id' => (int) $group->attribute( 'id' ),
            'uuid' => (string) $group->attribute( 'uuid' ),
            'label' => (string) $group->attribute( 'label' ) !== '' ? (string) $group->attribute( 'label' ) : substr( (string) $group->attribute( 'uuid' ), 0, 8 ),
            'type' => (string) $group->attribute( 'type' ),
            'state' => (string) $group->attribute( 'state' ),
            'created' => (int) $group->attribute( 'created' ),
            'age' => self::duration( expQueue::now() - (int) $group->attribute( 'created' ) ),
            'user_id' => (int) $group->attribute( 'user_id' ),
            'progress' => $p,
            'can_pause' => $group->attribute( 'state' ) === expQueueGroup::STATE_ACTIVE,
            'can_resume' => $group->attribute( 'state' ) === expQueueGroup::STATE_PAUSED,
            'can_cancel' => in_array( $group->attribute( 'state' ), array( expQueueGroup::STATE_ACTIVE, expQueueGroup::STATE_PAUSED ), true ),
        );
    }

    /** @return string the user's name for a user id (or #id) */
    public static function userName( $id )
    {
        static $names = array();
        $id = (int) $id;
        if ( !isset( $names[$id] ) )
        {
            $object = $id ? eZContentObject::fetch( $id ) : null;
            $names[$id] = $object ? (string) $object->attribute( 'name' ) : '#' . $id;
        }
        return $names[$id];
    }

    /**
     * Where to go back to after an action: a path of this module only (never another site).
     *
     * @return string
     */
    public static function backTo( $value )
    {
        $value = '/' . ltrim( (string) $value, '/' );
        if ( !preg_match( '#^/expqueue/(dashboard|list|job|group)(/[A-Za-z0-9_()./:-]*)?$#', $value ) )
            return '/expqueue/dashboard';
        return $value;
    }

    /** Keeps a short message for the next page view (the session). */
    public static function flash( $message, $kind = 'ok' )
    {
        eZHTTPTool::instance()->setSessionVariable( 'ExpQueueFlash', array( 'message' => (string) $message, 'kind' => (string) $kind ) );
    }

    /** @return array|null the message left by the last action, removed once read */
    public static function takeFlash()
    {
        $http = eZHTTPTool::instance();
        if ( !$http->hasSessionVariable( 'ExpQueueFlash' ) )
            return null;
        $flash = $http->sessionVariable( 'ExpQueueFlash' );
        $http->removeSessionVariable( 'ExpQueueFlash' );
        return is_array( $flash ) ? $flash : null;
    }

    /** The page shown when the tables are missing. */
    public static function notInstalled()
    {
        $tpl = eZTemplate::factory();
        $tpl->setVariable( 'view', 'dashboard' );
        $tpl->setVariable( 'can_manage', false );
        $tpl->setVariable( 'flash', array( 'kind' => 'bad', 'message' => ezpI18n::tr( 'extension/expqueue', 'The tables of the job queue are missing in this database. Run ./console ext:expqueue:install-schema on the server.' ) ) );
        return self::result( 'dashboard', $tpl->fetch( 'design:expqueue/missing.tpl' ), array( ezpI18n::tr( 'extension/expqueue', 'Dashboard' ) ) );
    }
}
