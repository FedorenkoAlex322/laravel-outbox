<?php

namespace Outbox;

use Illuminate\Support\ServiceProvider;
use Outbox\Cleanup\ArchiveCleanupStrategy;
use Outbox\Cleanup\DeleteCleanupStrategy;
use Outbox\Commands\OutboxCleanupCommand;
use Outbox\Commands\OutboxStatusCommand;
use Outbox\Commands\OutboxWorkerCommand;
use Outbox\Contracts\CleanupStrategy;
use Outbox\Contracts\LockManager;
use Outbox\Contracts\OutboxManager;
use Outbox\Contracts\OutboxStorage;
use Outbox\Contracts\RetryStrategy;
use Outbox\Contracts\Transport;
use Outbox\Locking\DatabaseLockManager;
use Outbox\Locking\RedisLockManager;
use Outbox\Retry\ExponentialBackoffStrategy;
use Outbox\Storage\EloquentOutboxStorage;
use Outbox\Transport\LaravelQueueTransport;
use Outbox\Transport\NullTransport;
use Outbox\Worker\OutboxProcessor;

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
