<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * expqueue.noop: does nothing and is done. For checking that a worker picks jobs up (the dashboard's "Send a test
 * job", ./console ext:expqueue:enqueue expqueue.noop) and for measuring the queue's own overhead.
 */
class expQueueNoopHandler extends expQueueHandlerBase
{
    public function run( expQueueJob $job, expQueueContext $context )
    {
        $payload = $job->payloadArray();
        return array( 'ok' => true, 'echo' => isset( $payload['echo'] ) ? expQueueJob::shorten( (string) $payload['echo'], 200 ) : null,
                      'worker_user' => expQueueLock::processUser(), 'attempt' => $context->attempt() );
    }

    public function describe( expQueueJob $job )
    {
        $payload = $job->payloadArray();
        return 'No operation' . ( isset( $payload['echo'] ) ? ': ' . (string) $payload['echo'] : '' );
    }
}
