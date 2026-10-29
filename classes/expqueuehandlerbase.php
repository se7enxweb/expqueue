<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * A base for handlers with the optional parts: validate() is called when a job is queued (a wrong payload is refused
 * there and then, not at 3 a.m. in a worker), describe() gives the one-line summary the admin pages show.
 */
abstract class expQueueHandlerBase implements expQueueHandler
{
    /**
     * Checks the payload of a job that is being queued.
     *
     * @param array $payload
     * @return string|null an error, or null when the payload is fine
     */
    public function validate( array $payload )
    {
        return null;
    }

    /**
     * A one-line summary of a job for the admin pages and the CLI (never a secret).
     *
     * @param expQueueJob $job
     * @return string
     */
    public function describe( expQueueJob $job )
    {
        return '';
    }
}
