<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * Thrown by a handler for an error that another attempt cannot fix (invalid input, a refusal, a missing object): the
 * job goes to the dead letter at once.
 */
class expQueuePermanentException extends expQueueException
{
    /** @var string a short machine-readable class of the error (e.g. validation, not_found) */
    public $errorClass = '';

    public function __construct( $message, $errorClass = 'permanent', ?Throwable $previous = null )
    {
        parent::__construct( (string) $message, 0, $previous );
        $this->errorClass = (string) $errorClass;
    }
}
