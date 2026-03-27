<?php

namespace FedorenkoAlex322\LaravelOutbox\Contracts;

interface LockManager
{
    /**
     * Acquire a lock. Returns true if lock was acquired.
     */
    public function acquire(string $key, int $ttlSeconds): bool;

    /**
     * Release a previously acquired lock.
     */
    public function release(string $key): void;

    /**
     * Extend an existing lock's TTL.
     */
    public function extend(string $key, int $ttlSeconds): bool;
}
