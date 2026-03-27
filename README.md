# Laravel Outbox

Outbox Pattern implementation for Laravel -- reliable event delivery from database to external systems.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-FF2D20.svg)](https://laravel.com)

---

## Problem Statement

When your application writes data to a database and then publishes an event to a message queue, you face a fundamental **dual-write consistency problem**.

Consider a typical order creation flow:

```php
// Naive approach -- DO NOT use in production
$order = Order::create([...]);
dispatch(new OrderCreatedJob($order)); // What if this fails?
```

Three failure scenarios break this approach:

1. **Database commits, dispatch fails.** The order exists but no event is ever published. Downstream systems never learn about it.
2. **Dispatch succeeds, database rolls back.** An event is published for an order that does not exist. Downstream systems process phantom data.
3. **Application crashes between the two operations.** The system is left in an inconsistent state with no reliable way to recover.

The root cause is that a database transaction and a queue dispatch are **two separate operations** that cannot be made atomic together. No amount of try/catch or retry logic can fully eliminate the window of inconsistency.

The **Outbox Pattern** solves this by turning the dual-write into a single atomic database write.

## How the Outbox Pattern Works

Instead of dispatching events directly, the application writes them to an `outbox_events` table **within the same database transaction** as the business data. A background worker then polls this table and delivers events to the transport (queue, webhook, etc.).

```
Your Application
       |
       | DB::transaction()
       v
  +---------+     +------------------+
  | orders  |     | outbox_events    |
  | (data)  |     | (event record)   |
  +---------+     +------------------+
                          |
                    Outbox Worker (polling)
                          |
                          v
                  +----------------+
                  | Transport      |
                  | (Queue/Custom) |
                  +----------------+
                          |
                          v
                  Downstream Systems
```

**Guarantees:**

- If the transaction commits, the event is stored. The worker will eventually deliver it.
- If the transaction rolls back, neither the data nor the event is persisted. No phantom events.
- Failed deliveries are retried with exponential backoff until the maximum attempt count is reached.

## Installation

```bash
composer require dobro/laravel-outbox
```

Publish the configuration and migration files:

```bash
php artisan vendor:publish --tag=outbox-config
php artisan vendor:publish --tag=outbox-migrations
php artisan migrate
```

The package auto-discovers its service provider via Laravel's package discovery. No manual registration is needed.

## Quick Start

### Storing Events

Store outbox events **inside your business transaction** to guarantee atomicity:

```php
use Illuminate\Support\Facades\DB;
use Outbox\DTOs\OutboxEventDTO;
use Outbox\Facades\Outbox;

DB::transaction(function () {
    $order = Order::create([
        'user_id' => auth()->id(),
        'total' => 9999,
        'status' => 'confirmed',
    ]);

    Outbox::store(OutboxEventDTO::make(
        type: 'order.created',
        payload: [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total' => $order->total,
        ],
        metadata: ['source' => 'api', 'ip' => request()->ip()],
        idempotencyKey: "order-created-{$order->id}",
    ));
});
```

The `store()` call writes a row to the `outbox_events` table within the same transaction. If the transaction rolls back, the event is never persisted.

### Running the Worker

Start the outbox worker to poll and deliver events:

```bash
# Run continuously (production)
php artisan outbox:work

# Process a single batch and exit (useful for cron or testing)
php artisan outbox:work --once

# Override batch size and poll interval
php artisan outbox:work --batch-size=50 --poll-interval=2

# Set memory limit (MB) before automatic restart
php artisan outbox:work --memory-limit=256
```

The worker supports graceful shutdown via SIGINT/SIGTERM signals.

### Cleanup

Remove old processed events to prevent unbounded table growth:

```bash
# Clean up events older than the configured retention period
php artisan outbox:cleanup
```

Schedule it in your `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:cleanup')->daily();
Schedule::command('outbox:work')->runInBackground()->withoutOverlapping();
```

### Status

View current outbox statistics:

```bash
php artisan outbox:status
```

Output:

```
+---------------------+-------+
| Metric              | Value |
+---------------------+-------+
| Pending             | 12    |
| Processing          | 3     |
| Processed           | 1847  |
| Failed              | 0     |
| Oldest Pending (min)| 0.45  |
+---------------------+-------+
```

## Configuration

All options are available in `config/outbox.php`. Most can be overridden via environment variables.

### Table and Connection

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `table` | `OUTBOX_TABLE` | `outbox_events` | Database table name for outbox events |
| `connection` | `OUTBOX_CONNECTION` | `null` (default) | Database connection name. Set to `null` to use the application default |

### Transport

The transport determines how events are delivered to external systems.

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `transport.driver` | `OUTBOX_TRANSPORT` | `queue` | Transport driver: `queue` or `null` |
| `transport.queue.connection` | `OUTBOX_QUEUE_CONNECTION` | `null` (default) | Queue connection for the `queue` driver |
| `transport.queue.queue` | `OUTBOX_QUEUE_NAME` | `outbox` | Queue name for dispatched jobs |

The `null` transport discards events silently -- useful for testing.

### Worker

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `worker.batch_size` | `OUTBOX_BATCH_SIZE` | `100` | Number of events fetched per polling cycle |
| `worker.poll_interval` | `OUTBOX_POLL_INTERVAL` | `1.0` | Seconds between polls when events are available |
| `worker.lock_timeout` | `OUTBOX_LOCK_TIMEOUT` | `300` | Seconds before a processing lock is considered stale |
| `worker.memory_limit` | `OUTBOX_MEMORY_LIMIT` | `128` | Memory limit in MB before the worker restarts |
| `worker.sleep_when_empty` | `OUTBOX_SLEEP_WHEN_EMPTY` | `5` | Seconds to sleep when no events are pending |

### Retry

Failed events are retried with exponential backoff: `base_delay * (multiplier ^ attempt)`, capped at `max_delay`, with 0-25% random jitter.

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `retry.strategy` | -- | `exponential` | Retry strategy. Use a class name for custom strategies |
| `retry.max_attempts` | `OUTBOX_MAX_ATTEMPTS` | `5` | Maximum delivery attempts before permanent failure |
| `retry.base_delay` | `OUTBOX_RETRY_BASE_DELAY` | `60` | Base delay in seconds for the first retry |
| `retry.multiplier` | `OUTBOX_RETRY_MULTIPLIER` | `2.0` | Delay multiplier for each subsequent attempt |
| `retry.max_delay` | `OUTBOX_RETRY_MAX_DELAY` | `3600` | Maximum delay cap in seconds (1 hour) |

With default settings, retries occur approximately at: 60s, 120s, 240s, 480s (with jitter).

### Locking

Locking prevents multiple workers from processing the same events concurrently.

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `locking.driver` | `OUTBOX_LOCK_DRIVER` | `database` | Lock driver: `database` or `redis` |
| `locking.redis.connection` | `OUTBOX_REDIS_CONNECTION` | `default` | Redis connection for the `redis` driver |
| `locking.redis.prefix` | -- | `outbox:lock:` | Redis key prefix for locks |

The `database` driver uses `SELECT FOR UPDATE SKIP LOCKED` (MySQL 8+, PostgreSQL 9.5+) with automatic fallback to `FOR UPDATE` for SQLite and older databases.

### Cleanup

| Option | Env Variable | Default | Description |
|--------|-------------|---------|-------------|
| `cleanup.strategy` | `OUTBOX_CLEANUP_STRATEGY` | `delete` | Cleanup strategy: `delete` or `archive` |
| `cleanup.retain_days` | `OUTBOX_RETAIN_DAYS` | `7` | Days to retain processed events before cleanup |
| `cleanup.batch_size` | `OUTBOX_CLEANUP_BATCH_SIZE` | `1000` | Number of events removed per cleanup batch |
| `cleanup.archive_table` | `OUTBOX_ARCHIVE_TABLE` | `outbox_events_archive` | Target table for the `archive` strategy |

### Events

| Option | Default | Description |
|--------|---------|-------------|
| `events.enabled` | `true` | Dispatch Laravel events on outbox lifecycle changes |

## Architecture

### Components

| Component | Contract | Default Implementation | Purpose |
|-----------|----------|----------------------|---------|
| Manager | `OutboxManager` | `DefaultOutboxManager` | Facade entry point -- coordinates store, process, cleanup |
| Storage | `OutboxStorage` | `EloquentOutboxStorage` | Database operations: store, fetch, lock, mark |
| Transport | `Transport` | `LaravelQueueTransport` | Delivers events to external systems |
| Retry | `RetryStrategy` | `ExponentialBackoffStrategy` | Determines retry eligibility and delay |
| Lock | `LockManager` | `DatabaseLockManager` | Distributed locking for concurrent workers |
| Cleanup | `CleanupStrategy` | `DeleteCleanupStrategy` | Removes or archives old processed events |

### Event Lifecycle

```
                         store()
                           |
                           v
                       [pending]
                           |
                    fetchAndLock()
                           |
                           v
                     [processing]
                      /         \
              success /           \ failure
                    /               \
                   v                 v
            [processed]          [failed]
                                     |
                              shouldRetry()?
                              /            \
                           yes              no
                            |                |
                            v                v
                       [pending]     permanently [failed]
                    (with delay)
```

### Laravel Events

When `outbox.events.enabled` is `true`, the following events are dispatched:

| Event | Fired When | Properties |
|-------|-----------|------------|
| `OutboxEventStored` | Event written to outbox table | `OutboxEvent $outboxEvent` |
| `OutboxEventProcessed` | Event successfully delivered via transport | `OutboxEvent $outboxEvent` |
| `OutboxEventFailed` | Transport delivery failed | `OutboxEvent $outboxEvent`, `string $error`, `bool $willRetry` |

Listen for these events to add logging, metrics, alerting, or custom behavior:

```php
use Outbox\Events\OutboxEventFailed;

class OutboxFailureListener
{
    public function handle(OutboxEventFailed $event): void
    {
        if (! $event->willRetry) {
            Log::critical('Outbox event permanently failed', [
                'uuid' => $event->outboxEvent->uuid,
                'type' => $event->outboxEvent->type,
                'error' => $event->error,
                'attempts' => $event->outboxEvent->attempts,
            ]);
        }
    }
}
```

### Database Schema

The `outbox_events` table is optimized for the worker polling pattern:

| Column | Type | Description |
|--------|------|-------------|
| `id` | `bigint` (PK) | Auto-increment for ordering guarantee |
| `uuid` | `uuid` (unique) | Public identifier for external systems and deduplication |
| `type` | `varchar(255)` | Event type (e.g. `order.created`, `user.registered`) |
| `payload` | `json` | Event data |
| `metadata` | `json` (nullable) | Optional context (source, IP, trace ID, etc.) |
| `idempotency_key` | `varchar(255)` (unique, nullable) | Consumer-side deduplication key |
| `status` | `varchar(20)` | `pending`, `processing`, `processed`, `failed` |
| `attempts` | `smallint` | Delivery attempt counter |
| `last_error` | `text` (nullable) | Error message from the last failed attempt |
| `next_retry_at` | `timestamp` (nullable) | Scheduled time for the next retry |
| `locked_by` | `varchar(255)` (nullable) | Worker ID holding the lock |
| `locked_until` | `timestamp` (nullable) | Lock expiration time |
| `processed_at` | `timestamp` (nullable) | Time the event was successfully delivered |

**Indexes:**

- `idx_outbox_worker_poll` on `(status, next_retry_at, created_at)` -- worker polling query
- `idx_outbox_cleanup` on `(status, processed_at)` -- cleanup query

## Extending

### Custom Transport

Implement the `Transport` contract to deliver events to any external system:

```php
namespace App\Outbox;

use Outbox\Contracts\Transport;
use Outbox\Models\OutboxEvent;
use Illuminate\Support\Facades\Http;

class WebhookTransport implements Transport
{
    public function __construct(
        private readonly string $webhookUrl,
    ) {}

    public function send(OutboxEvent $event): void
    {
        $response = Http::timeout(10)->post($this->webhookUrl, [
            'uuid' => $event->uuid,
            'type' => $event->type,
            'payload' => $event->payload,
            'metadata' => $event->metadata,
        ]);

        $response->throw(); // Throws on 4xx/5xx -- triggers retry
    }

    public function getName(): string
    {
        return 'webhook';
    }
}
```

Register it in a service provider:

```php
use Outbox\Contracts\Transport;
use App\Outbox\WebhookTransport;

$this->app->singleton(Transport::class, function () {
    return new WebhookTransport(config('services.webhook.url'));
});
```

### Custom Retry Strategy

Implement the `RetryStrategy` contract:

```php
namespace App\Outbox;

use Outbox\Contracts\RetryStrategy;

class FixedDelayRetryStrategy implements RetryStrategy
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $delaySeconds = 30,
    ) {}

    public function shouldRetry(int $attempts): bool
    {
        return $attempts < $this->maxAttempts;
    }

    public function getDelay(int $attempts): int
    {
        return $this->delaySeconds;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}
```

Register it:

```php
use Outbox\Contracts\RetryStrategy;
use App\Outbox\FixedDelayRetryStrategy;

$this->app->singleton(RetryStrategy::class, FixedDelayRetryStrategy::class);
```

### Custom Cleanup Strategy

Implement the `CleanupStrategy` contract:

```php
namespace App\Outbox;

use Outbox\Contracts\CleanupStrategy;
use Outbox\Models\OutboxEvent;

class SoftDeleteCleanupStrategy implements CleanupStrategy
{
    public function cleanup(int $retentionDays, int $batchSize): int
    {
        return OutboxEvent::processed()
            ->olderThan(now()->subDays($retentionDays))
            ->limit($batchSize)
            ->update(['deleted_at' => now()]);
    }
}
```

### Custom Event Data

Implement `OutboxEventData` for domain-specific event objects:

```php
namespace App\Outbox;

use Outbox\Contracts\OutboxEventData;

final readonly class OrderCreatedEvent implements OutboxEventData
{
    public function __construct(
        private int $orderId,
        private int $userId,
        private int $totalCents,
    ) {}

    public function getType(): string
    {
        return 'order.created';
    }

    public function getPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'total_cents' => $this->totalCents,
        ];
    }

    public function getMetadata(): array
    {
        return ['source' => 'checkout'];
    }

    public function getIdempotencyKey(): ?string
    {
        return "order-created-{$this->orderId}";
    }
}
```

Usage:

```php
DB::transaction(function () use ($order) {
    Outbox::store(new OrderCreatedEvent(
        orderId: $order->id,
        userId: $order->user_id,
        totalCents: $order->total,
    ));
});
```

## Trade-offs and Design Decisions

### Polling vs Push (CDC/Triggers)

This package uses **polling** -- the worker periodically queries the `outbox_events` table for pending events.

| Approach | Pros | Cons |
|----------|------|------|
| **Polling** (this package) | Simple, portable, works with any database | Adds latency (configurable, default 1s), puts read load on DB |
| **CDC** (Debezium, etc.) | Near-zero latency, no polling load | Complex infrastructure, database-specific, harder to operate |
| **Triggers** | Low latency | Database-specific, hard to test, debugging is difficult |

Polling is the right choice for most Laravel applications. If you need sub-second latency at scale, consider CDC-based solutions like Debezium.

### At-Least-Once Delivery

This package guarantees **at-least-once** delivery. Events may be delivered more than once in edge cases:

- The worker sends an event to the transport, but crashes before marking it as processed.
- On restart, the worker picks up and redelivers the same event.

**Consumers must be idempotent.** Use the `uuid` or `idempotency_key` fields for deduplication on the consumer side.

### Database Table Growth

The `outbox_events` table grows with every event. Without cleanup, it will become a performance bottleneck.

Mitigation strategies:

- **Schedule `outbox:cleanup` daily** -- removes processed events older than the retention period.
- **Use the `archive` strategy** -- moves old events to a separate table instead of deleting them.
- **Monitor with `outbox:status`** -- watch the pending count and oldest pending age for anomalies.

### Concurrency

Multiple workers can run safely in parallel. The `SELECT FOR UPDATE SKIP LOCKED` mechanism ensures each event is processed by exactly one worker at a time. Stale locks from crashed workers are automatically released after the configured `lock_timeout`.

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- MySQL 8+, PostgreSQL 9.5+, or SQLite (with fallback locking)
- Redis (optional, for Redis-based lock driver)

## Testing

```bash
vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
