<?php

namespace Outbox\Locking;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Outbox\Contracts\LockManager;

class RedisLockManager implements LockManager
{
    public function acquire(string $key, int $ttlSeconds): bool
    {
        $store = $this->getStore();

        return $store->lock($key, $ttlSeconds)->get();
    }

    public function release(string $key): void
    {
        $this->getStore()->lock($key)->forceRelease();
    }

    public function extend(string $key, int $ttlSeconds): bool
    {
        $this->release($key);

        return $this->acquire($key, $ttlSeconds);
    }

    private function getStore(): Repository
    {
        $connection = config('outbox.locking.redis.connection', 'default');

        return Cache::store('redis');
    }
}
