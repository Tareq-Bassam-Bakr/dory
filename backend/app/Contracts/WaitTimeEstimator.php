<?php

namespace App\Contracts;

use App\Models\Queue;
use App\Services\Queues\WaitEstimate;

/**
 * Estimates how long a customer will wait (docs/WAIT_TIME_ESTIMATION.md).
 *
 * Implementations must be deterministic for the same data, cheap enough to
 * run on every position request, and must never block a queue operation.
 * A smarter (e.g. ML) estimator can replace the default binding later
 * without changing callers.
 */
interface WaitTimeEstimator
{
    /**
     * @param  int  $peopleAhead  Waiting customers who will be called first.
     */
    public function estimate(Queue $queue, int $peopleAhead): WaitEstimate;

    /**
     * Prepares estimates for many queues at once, so that listing queues
     * costs a fixed number of queries instead of a few per queue. Optional
     * for callers: estimate() must work without it.
     *
     * @param  iterable<Queue>  $queues
     */
    public function warm(iterable $queues): void;
}
