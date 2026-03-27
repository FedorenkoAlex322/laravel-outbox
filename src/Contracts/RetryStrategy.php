<?php

namespace Outbox\Contracts;

interface RetryStrategy
{
    /**
     * Whether the event should be retried given its current attempt count.
     */
    public function shouldRetry(int $attempts): bool;

    /**
     * Calculate delay in seconds before next retry.
     */
    public function getDelay(int $attempts): int;

    /**
     * Get maximum number of attempts.
     */
    public function getMaxAttempts(): int;
}
