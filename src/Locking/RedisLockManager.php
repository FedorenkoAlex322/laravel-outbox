<?php

namespace FedorenkoAlex322\LaravelOutbox\Locking;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use FedorenkoAlex322\LaravelOutbox\Contracts\LockManager;

class RedisLockManager implements LockManager
{
    private ?string $owner = null;

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $store = $this->getStore();
        $lock = $store->lock($key, $ttlSeconds);
        $acquired = $lock->get();

        if ($acquired) {
            $this->owner = $lock->owner();
        }

        return $acquired;
    }

    public function release(string $key): void
    {
        $this->getStore()->restoreLock($key, $this->owner)->release();
        $this->owner = null;
    }

    public function extend(string $key, int $ttlSeconds): bool
    {
        $this->getStore()->lock($key)->forceRelease();

        return $this->acquire($key, $ttlSeconds);
    }

    private function getStore(): Repository
    {
        $store = config('outbox.locking.redis.store', 'redis');

        return Cache::store($store);
    }
}
