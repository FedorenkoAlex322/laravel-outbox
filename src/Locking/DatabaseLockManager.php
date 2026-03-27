<?php

namespace Outbox\Locking;

use Illuminate\Support\Facades\Cache;
use Outbox\Contracts\LockManager;

class DatabaseLockManager implements LockManager
{
    public function acquire(string $key, int $ttlSeconds): bool
    {
        return Cache::lock($key, $ttlSeconds)->get();
    }

    public function release(string $key): void
    {
        Cache::lock($key)->forceRelease();
    }

    public function extend(string $key, int $ttlSeconds): bool
    {
        // Laravel's cache lock doesn't have native extend.
        // Release and re-acquire as a workaround.
        $this->release($key);

        return $this->acquire($key, $ttlSeconds);
    }
}
