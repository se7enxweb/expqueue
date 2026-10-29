<?php
/**
 * File containing the expQueueEvent class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * The history of a job (table expqueue_event), append-only, removed with the job. Kinds: enqueue, duplicate, ready,
 * claim, heartbeat (not stored), progress (not stored), log, retry, defer, hold, done, dead, cancel, reap, pause,
 * resume, requeue.
 */
class expQueueEvent extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => '', 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'job_id' => $int( 'JobID' ),
                'group_id' => $int( 'GroupID' ),
                'ts' => $int( 'Timestamp' ),
                'kind' => $str( 'Kind' ),
                'attempt' => $int( 'Attempt' ),
                'worker' => $str( 'Worker' ),
                'message' => $str( 'Message' ),
                'data' => $str( 'Data' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array( 'data_summary' => 'dataSummary' ),
            'class_name' => 'expQueueEvent',
            'sort' => array( 'id' => 'asc' ),
            'name' => 'expqueue_event',
        );
    }

    /**
     * Appends an event.
     *
     * @return expQueueEvent
     */
    public static function add( $jobID, $groupID, $kind, $message = '', array $data = array(), $attempt = 0, $worker = '' )
    {
        $event = new self( array(
            'job_id' => (int) $jobID,
            'group_id' => (int) $groupID,
            'ts' => time(),
            'kind' => substr( (string) $kind, 0, 16 ),
            'attempt' => (int) $attempt,
            'worker' => substr( (string) $worker, 0, 80 ),
            'message' => expQueueJob::shorten( (string) $message, 255 ),
            'data' => $data ? json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ) : '',
        ) );
        $event->store();
        return $event;
    }

    /** @return expQueueEvent[] the events of a job, oldest first */
    public static function fetchForJob( $jobID, $limit = 200 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, array( 'job_id' => (int) $jobID ),
                                                    array( 'id' => 'asc' ), array( 'offset' => 0, 'length' => (int) $limit ), true );
    }

    /** @return array */
    public function dataSummary()
    {
        return expQueueJob::summarise( expQueueJob::decode( $this->attribute( 'data' ) ) );
    }
}
