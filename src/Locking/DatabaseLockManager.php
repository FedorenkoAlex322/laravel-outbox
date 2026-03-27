<?php

namespace Outbox\Locking;

use Illuminate\Support\Facades\Cache;
use Outbox\Contracts\LockManager;

class DatabaseLockManager implements LockManager
{
    private ?string $owner = null;

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $lock = Cache::lock($key, $ttlSeconds);
        $acquired = $lock->get();

        if ($acquired) {
            $this->owner = $lock->owner();
        }

        return $acquired;
    }

    public function release(string $key): void
    {
        Cache::restoreLock($key, $this->owner)->release();
        $this->owner = null;
    }

    public function extend(string $key, int $ttlSeconds): bool
    {
        // Laravel's cache lock doesn't have native extend.
        // Force release and re-acquire as best effort.
        Cache::lock($key)->forceRelease();

        return $this->acquire($key, $ttlSeconds);
    }
}
