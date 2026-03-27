<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Feature;

use FedorenkoAlex322\LaravelOutbox\Contracts\CleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\LockManager;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Contracts\RetryStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\Transport;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;
use FedorenkoAlex322\LaravelOutbox\Worker\OutboxProcessor;

class ServiceProviderTest extends TestCase
{
    public function test_retry_strategy_resolves_from_container(): void
    {
        $instance = $this->app->make(RetryStrategy::class);

        $this->assertInstanceOf(RetryStrategy::class, $instance);
    }

    public function test_outbox_storage_resolves_from_container(): void
    {
        $instance = $this->app->make(OutboxStorage::class);

        $this->assertInstanceOf(OutboxStorage::class, $instance);
    }

    public function test_transport_resolves_from_container(): void
    {
        $instance = $this->app->make(Transport::class);

        $this->assertInstanceOf(Transport::class, $instance);
    }

    public function test_lock_manager_resolves_from_container(): void
    {
        $instance = $this->app->make(LockManager::class);

        $this->assertInstanceOf(LockManager::class, $instance);
    }

    public function test_cleanup_strategy_resolves_from_container(): void
    {
        $instance = $this->app->make(CleanupStrategy::class);

        $this->assertInstanceOf(CleanupStrategy::class, $instance);
    }

    public function test_outbox_processor_resolves_from_container(): void
    {
        $instance = $this->app->make(OutboxProcessor::class);

        $this->assertInstanceOf(OutboxProcessor::class, $instance);
    }

    public function test_outbox_manager_resolves_from_container(): void
    {
        $instance = $this->app->make(OutboxManager::class);

        $this->assertInstanceOf(OutboxManager::class, $instance);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(config('outbox.table'));
        $this->assertSame('outbox_events', config('outbox.table'));
        $this->assertIsArray(config('outbox.retry'));
        $this->assertIsArray(config('outbox.worker'));
        $this->assertIsArray(config('outbox.cleanup'));
    }
}
