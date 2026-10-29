<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * Thrown by a handler for an error that may go away: a timeout, a 429 or 5xx answer, a resource that is busy. The job
 * is retried with exponential backoff and jitter, never sooner than $retryAfter seconds, until its max_attempts are
 * used up; then it goes to the dead letter.
 */
class expQueueRetryableException extends expQueueException
{
    /** @var int seconds to wait at least (a Retry-After header), 0 = the backoff decides */
    public $retryAfter = 0;

    /** @var string a short machine-readable class of the error (e.g. http_429, timeout) */
    public $errorClass = '';

    public function __construct( $message, $retryAfter = 0, $errorClass = 'retryable', ?Throwable $previous = null )
    {
        parent::__construct( (string) $message, 0, $previous );
        $this->retryAfter = max( 0, (int) $retryAfter );
        $this->errorClass = (string) $errorClass;
    }
}
