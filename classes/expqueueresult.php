<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * What a handler's run() returns when an array (done) is not enough.
 *
 *   expQueueResult::done( array $result )            the job is done
 *   expQueueResult::retry( $message, $retryAfter )   try again later (counts as an attempt)
 *   expQueueResult::fail( $message )                 dead letter at once
 *   expQueueResult::defer( $seconds, $message )      not now: back to the queue without using up an attempt
 *   expQueueResult::hold( $message )                 held until someone resumes it (e.g. a budget is used up)
 *   expQueueResult::cancelled( $message )            stopped because it was cancelled
 */
class expQueueResult
{
    const DONE = 'done';
    const RETRY = 'retry';
    const FAIL = 'fail';
    const DEFER = 'defer';
    const HOLD = 'hold';
    const CANCELLED = 'cancelled';

    /** @var string */
    public $outcome;

    /** @var array */
    public $data;

    /** @var string */
    public $message;

    /** @var int */
    public $seconds;

    /** @var string */
    public $errorClass;

    public function __construct( $outcome, array $data = array(), $message = '', $seconds = 0, $errorClass = '' )
    {
        $this->outcome = $outcome;
        $this->data = $data;
        $this->message = (string) $message;
        $this->seconds = max( 0, (int) $seconds );
        $this->errorClass = (string) $errorClass;
    }

    public static function done( array $data = array(), $message = '' )
    {
        return new self( self::DONE, $data, $message );
    }

    public static function retry( $message, $retryAfter = 0, $errorClass = 'retryable' )
    {
        return new self( self::RETRY, array(), $message, $retryAfter, $errorClass );
    }

    public static function fail( $message, $errorClass = 'permanent' )
    {
        return new self( self::FAIL, array(), $message, 0, $errorClass );
    }

    public static function defer( $seconds, $message = '' )
    {
        return new self( self::DEFER, array(), $message, $seconds );
    }

    public static function hold( $message = '' )
    {
        return new self( self::HOLD, array(), $message );
    }

    public static function cancelled( $message = '' )
    {
        return new self( self::CANCELLED, array(), $message );
    }

    /**
     * The result of whatever run() returned.
     *
     * @param mixed $value
     * @return expQueueResult
     */
    public static function from( $value )
    {
        if ( $value instanceof self )
            return $value;
        if ( is_array( $value ) )
            return self::done( $value );
        if ( $value === null || $value === true )
            return self::done();
        if ( $value === false )
            return self::retry( 'the handler returned false' );
        return self::done( array( 'value' => is_scalar( $value ) ? $value : gettype( $value ) ) );
    }
}
