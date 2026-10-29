<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * A job handler: the code that runs the jobs of one type. Registered in expqueue.ini:
 *
 *   [JobType_<type>]
 *   Handler=<class implementing expQueueHandler>
 *
 * run() is called by a worker (never in a web request), as the user who queued the job, outside any database
 * transaction. It returns
 *   - nothing or an array: the job is done, the array is its result (JSON);
 *   - an expQueueResult: done, retry, fail, defer, hold or cancelled, with a message and a result;
 * and it throws expQueueRetryableException (try again later, honouring retry-after), expQueuePermanentException
 * (dead letter at once) or expQueueCancelledException (cancelled while it ran). Any other exception counts as
 * retryable.
 *
 * Long work calls $context->heartbeat() between steps (this extends the lease, so the reaper does not hand the job to
 * another worker) and $context->throwIfCancelled() (cooperative cancellation). Work that costs money or cannot be
 * repeated stores its outcome with $context->saveCheckpoint() before it applies it, and looks for
 * $context->checkpoint() first, so a rerun after a crash applies the stored outcome instead of paying twice.
 */
interface expQueueHandler
{
    /**
     * @param expQueueJob $job
     * @param expQueueContext $context
     * @return expQueueResult|array|null
     */
    public function run( expQueueJob $job, expQueueContext $context );
}
