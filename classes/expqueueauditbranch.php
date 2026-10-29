<?php
/**
 * File containing the expQueueAuditBranch class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The audit taxonomy branch of expqueue (registered in settings/audit.ini.append.php): system.queue.<action>, one
 * event per operator action on the queue and per job that ends in the dead letter. The ordinary life of a job
 * (enqueue, claim, done) is kept in expqueue_event, not in the audit trail.
 */
class expQueueAuditBranch implements expAuditTaxonomyBranch
{
    const ACTIONS = array(
        'job.cancel' => array( 'Queue: job cancelled', 'notice' ),
        'job.retry' => array( 'Queue: job requeued', 'notice' ),
        'job.dead' => array( 'Queue: job in the dead letter', 'warning' ),
        'group.cancel' => array( 'Queue: job group cancelled', 'notice' ),
        'pause' => array( 'Queue: paused (queue, class, resource or group)', 'notice' ),
        'resume' => array( 'Queue: resumed (queue, class, resource or group)', 'notice' ),
        'purge' => array( 'Queue: old jobs purged', 'info' ),
    );

    public function events()
    {
        $events = array();
        foreach ( self::ACTIONS as $action => $info )
            $events['system.queue.' . $action] = array( 'label' => $info[0], 'severity' => $info[1], 'channel' => 'system', 'default' => 'on' );
        return $events;
    }
}
