<?php
/**
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expqueue
 */

/**
 * expqueue.sleep: waits, in steps, reporting progress and honouring a cancel between the steps. For load and fairness
 * tests and for trying cancel, heartbeat and the reaper by hand.
 *
 * Payload: seconds (default 1, at most 3600; fractions allowed), steps (default 10), fail_rate (0..1: the share of
 * runs that end with a retryable error, for error injection), checkpoint (true: save a checkpoint after the first
 * step and skip the wait on a rerun that finds it).
 */
class expQueueSleepHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        if ( isset( $payload['seconds'] ) && ( !is_numeric( $payload['seconds'] ) || $payload['seconds'] < 0 || $payload['seconds'] > 3600 ) )
            return 'seconds must be a number from 0 to 3600';
        if ( isset( $payload['fail_rate'] ) && ( !is_numeric( $payload['fail_rate'] ) || $payload['fail_rate'] < 0 || $payload['fail_rate'] > 1 ) )
            return 'fail_rate must be a number from 0 to 1';
        return null;
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $payload = $job->payloadArray();
        $seconds = isset( $payload['seconds'] ) ? (float) $payload['seconds'] : 1.0;
        $steps = max( 1, min( 1000, isset( $payload['steps'] ) ? (int) $payload['steps'] : 10 ) );
        $stored = !empty( $payload['checkpoint'] ) ? $context->checkpoint() : null;
        if ( $stored !== null )
            return array( 'slept' => 0, 'from_checkpoint' => true, 'checkpoint' => $stored );
        $t0 = microtime( true );
        for ( $i = 1; $i <= $steps; $i++ )
        {
            usleep( (int) round( $seconds / $steps * 1000000 ) );
            $context->progress( $i, $steps );
            $context->throwIfCancelled();
            if ( $i === 1 && !empty( $payload['checkpoint'] ) )
                $context->saveCheckpoint( array( 'step' => 1, 'at' => time() ) );
        }
        if ( isset( $payload['fail_rate'] ) && mt_rand() / mt_getrandmax() < (float) $payload['fail_rate'] )
            throw new expQueueRetryableException( 'injected error (fail_rate ' . $payload['fail_rate'] . ')', 0, 'injected' );
        return array( 'slept' => round( microtime( true ) - $t0, 3 ), 'steps' => $steps, 'attempt' => $context->attempt(),
                      'start' => round( $t0, 3 ), 'end' => round( microtime( true ), 3 ) );
    }

    public function describe( expQueueJob $job )
    {
        $payload = $job->payloadArray();
        return 'Sleep ' . ( isset( $payload['seconds'] ) ? (float) $payload['seconds'] : 1 ) . ' s';
    }
}
