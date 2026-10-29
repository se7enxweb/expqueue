<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * expqueue.fail: fails on purpose, to try retries, backoff, the dead letter and the rate-limit adaptation.
 *
 * Payload: mode = retryable (default: a retryable error every time, so the job ends in the dead letter after
 * max_attempts), permanent (dead letter at once), exception (a plain exception, which counts as retryable),
 * rate_limit (reports a 429 with retry_after to the job's resource, then a retryable error);
 * retry_after (seconds, for retryable and rate_limit); succeed_on (an attempt number from which it succeeds).
 */
class expQueueFailHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        $mode = isset( $payload['mode'] ) ? (string) $payload['mode'] : 'retryable';
        return in_array( $mode, array( 'retryable', 'permanent', 'exception', 'rate_limit' ), true ) ? null : 'mode must be retryable, permanent, exception or rate_limit';
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $payload = $job->payloadArray();
        $mode = isset( $payload['mode'] ) ? (string) $payload['mode'] : 'retryable';
        $after = isset( $payload['retry_after'] ) ? (int) $payload['retry_after'] : 0;
        if ( isset( $payload['succeed_on'] ) && $context->attempt() >= (int) $payload['succeed_on'] )
            return array( 'succeeded_on_attempt' => $context->attempt() );
        switch ( $mode )
        {
            case 'permanent':
                throw new expQueuePermanentException( 'permanent failure on purpose', 'test_permanent' );
            case 'exception':
                throw new RuntimeException( 'a plain exception on purpose' );
            case 'rate_limit':
                $context->rateLimit( array( 'status' => 429, 'retry_after' => max( 1, $after ) ) );
                throw new expQueueRetryableException( 'rate limited on purpose (429)', max( 1, $after ), 'http_429' );
        }
        throw new expQueueRetryableException( 'retryable failure on purpose (attempt ' . $context->attempt() . ')', $after, 'test_retryable' );
    }

    public function describe( expQueueJob $job )
    {
        $payload = $job->payloadArray();
        return 'Fail on purpose (' . ( isset( $payload['mode'] ) ? (string) $payload['mode'] : 'retryable' ) . ')';
    }
}
