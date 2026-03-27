<?php

namespace FedorenkoAlex322\LaravelOutbox\Retry;

use FedorenkoAlex322\LaravelOutbox\Contracts\RetryStrategy;

class ExponentialBackoffStrategy implements RetryStrategy
{
    private int $maxAttempts;
    private int $baseDelay;
    private float $multiplier;
    private int $maxDelay;

    public function __construct()
    {
        $this->maxAttempts = config('outbox.retry.max_attempts', 5);
        $this->baseDelay = config('outbox.retry.base_delay', 60);
        $this->multiplier = config('outbox.retry.multiplier', 2.0);
        $this->maxDelay = config('outbox.retry.max_delay', 3600);
    }

    public function shouldRetry(int $attempts): bool
    {
        return $attempts < $this->maxAttempts;
    }

    public function getDelay(int $attempts): int
    {
        $delay = (int) ($this->baseDelay * ($this->multiplier ** ($attempts - 1)));

        // Cap at max delay
        $delay = min($delay, $this->maxDelay);

        // Add jitter (0-25% random addition)
        $jitter = (int) ($delay * 0.25 * (random_int(0, 100) / 100));

        return $delay + $jitter;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
