<?php

namespace FedorenkoAlex322\LaravelOutbox;

use Illuminate\Support\ServiceProvider;
use FedorenkoAlex322\LaravelOutbox\Cleanup\ArchiveCleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Cleanup\DeleteCleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Commands\OutboxCleanupCommand;
use FedorenkoAlex322\LaravelOutbox\Commands\OutboxStatusCommand;
use FedorenkoAlex322\LaravelOutbox\Commands\OutboxWorkerCommand;
use FedorenkoAlex322\LaravelOutbox\Contracts\CleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\LockManager;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Contracts\RetryStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\Transport;
use FedorenkoAlex322\LaravelOutbox\Locking\DatabaseLockManager;
use FedorenkoAlex322\LaravelOutbox\Locking\RedisLockManager;
use FedorenkoAlex322\LaravelOutbox\Retry\ExponentialBackoffStrategy;
use FedorenkoAlex322\LaravelOutbox\Storage\EloquentOutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Transport\LaravelQueueTransport;
use FedorenkoAlex322\LaravelOutbox\Transport\NullTransport;
use FedorenkoAlex322\LaravelOutbox\Worker\OutboxProcessor;

class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/outbox.php', 'outbox');

        $this->app->singleton(RetryStrategy::class, ExponentialBackoffStrategy::class);

        $this->app->singleton(OutboxStorage::class, EloquentOutboxStorage::class);

        $this->app->singleton(Transport::class, function ($app) {
            $driver = config('outbox.transport.driver', 'queue');

            return match ($driver) {
                'null' => new NullTransport(),
                default => new LaravelQueueTransport(),
            };
        });

        $this->app->singleton(LockManager::class, function ($app) {
            $driver = config('outbox.locking.driver', 'database');

            return match ($driver) {
                'redis' => new RedisLockManager(),
                default => new DatabaseLockManager(),
            };
        });

        $this->app->singleton(CleanupStrategy::class, function ($app) {
            $strategy = config('outbox.cleanup.strategy', 'delete');

            return match ($strategy) {
                'archive' => new ArchiveCleanupStrategy(),
                default => new DeleteCleanupStrategy(),
            };
        });

        $this->app->singleton(OutboxProcessor::class);
        $this->app->singleton(OutboxManager::class, DefaultOutboxManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/outbox.php' => config_path('outbox.php'),
            ], 'outbox-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'outbox-migrations');

            $this->commands([
                OutboxWorkerCommand::class,
                OutboxCleanupCommand::class,
                OutboxStatusCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
