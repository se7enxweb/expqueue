<?php
/**
 * File containing the expQueueGroup class.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * A set of jobs that belong together (table expqueue_group): "translate subtree X into ten languages", a package
 * import, a chain run. A group can be paused, resumed and cancelled as a whole; its progress is counted from its
 * jobs. States: active, paused, cancelled, done (no active job left).
 */
class expQueueGroup extends eZPersistentObject
{
    const STATE_ACTIVE = 'active';
    const STATE_PAUSED = 'paused';
    const STATE_CANCELLED = 'cancelled';
    const STATE_DONE = 'done';

    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'uuid' => $str( 'UUID' ),
                'label' => $str( 'Label' ),
                'type' => $str( 'Type' ),
                'user_id' => $int( 'UserID' ),
                'site' => $str( 'Site' ),
                'state' => $str( 'State', self::STATE_ACTIVE ),
                'total' => $int( 'Total' ),
                'budget_micros' => $int( 'BudgetMicros' ),
                'spent_micros' => $int( 'SpentMicros' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array( 'counts' => 'counts', 'progress' => 'progress' ),
            'class_name' => 'expQueueGroup',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expqueue_group',
        );
    }

    /** @return expQueueGroup|null */
    public static function fetchByID( $id )
    {
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int) $id ), true );
    }

    /** @return expQueueGroup|null */
    public static function fetchByUUID( $uuid )
    {
        if ( !preg_match( '/^[0-9a-f-]{36}$/', (string) $uuid ) )
            return null;
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'uuid' => (string) $uuid ), true );
    }

    /** @return expQueueGroup[] the newest groups */
    public static function fetchRecent( $limit = 20, $offset = 0 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null, null, array( 'id' => 'desc' ),
                                                    array( 'offset' => (int) $offset, 'length' => (int) $limit ), true );
    }

    /** @return array state => number of jobs of the group */
    public function counts()
    {
        return expQueue::countBy( 'state', array( 'group_id' => (int) $this->attribute( 'id' ) ) );
    }

    /** @return array done, failed (dead), cancelled, active, total, percent */
    public function progress()
    {
        $c = $this->counts();
        $get = function ( $s ) use ( $c ) { return isset( $c[$s] ) ? (int) $c[$s] : 0; };
        $total = array_sum( $c );
        $finished = $get( 'done' ) + $get( 'dead' ) + $get( 'cancelled' );
        return array( 'done' => $get( 'done' ), 'dead' => $get( 'dead' ), 'cancelled' => $get( 'cancelled' ),
                      'running' => $get( 'running' ), 'active' => $total - $finished, 'total' => $total,
                      'percent' => $total ? (int) floor( 100 * $finished / $total ) : 0 );
    }
}
