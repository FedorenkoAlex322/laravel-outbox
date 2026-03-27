# Laravel Outbox Pattern Package

## Project Overview

Composer-пакет для Laravel, реализующий Outbox Pattern для надежной доставки событий из БД во внешние системы. Решает проблему рассинхронизации DB / Queue (dual-write problem).

- **Namespace:** `FedorenkoAlex322\LaravelOutbox\`
- **PHP:** ^8.1
- **Laravel:** ^10 || ^11 || ^12
- **Package name:** `fedorenkoalex322/laravel-outbox`

## Architecture

### Core Design Decisions

- **Strategy Pattern** для всех заменяемых компонентов (transport, retry, locking, cleanup)
- **Polling-based worker** (SELECT FOR UPDATE SKIP LOCKED для MySQL 8+, fallback на FOR UPDATE)
- **At-least-once delivery** — UUID для consumer-side deduplication
- **User wraps** `store()` в свою DB транзакцию (суть Outbox Pattern)
- **Auto-increment ID** для ordering + UUID для external identity (не UUID PK для index performance)

### Component Map

| Contract | Default Implementation | Purpose |
|----------|----------------------|---------|
| `OutboxManager` | `DefaultOutboxManager` | Entry point (store, process, cleanup, stats) |
| `OutboxStorage` | `EloquentOutboxStorage` | DB persistence (store, fetchAndLock, mark*) |
| `Transport` | `LaravelQueueTransport` | Delivers events to external systems |
| `RetryStrategy` | `ExponentialBackoffStrategy` | Retry timing and eligibility |
| `LockManager` | `DatabaseLockManager` | Concurrent worker protection |
| `CleanupStrategy` | `DeleteCleanupStrategy` | Old event removal/archival |

### Event Lifecycle

```
pending → processing → processed
                    ↘ failed → (retry) → pending
                              (max attempts) → failed (terminal)
```

### File Structure

```
src/
├── Contracts/        7 interfaces (OutboxManager, OutboxStorage, Transport, RetryStrategy, LockManager, CleanupStrategy, OutboxEventData)
├── Models/           OutboxEvent (Eloquent)
├── DTOs/             OutboxEventDTO (readonly DTO)
├── Enums/            OutboxEventStatus (string-backed enum)
├── Storage/          EloquentOutboxStorage
├── Transport/        LaravelQueueTransport, NullTransport
├── Retry/            ExponentialBackoffStrategy
├── Locking/          DatabaseLockManager, RedisLockManager
├── Cleanup/          DeleteCleanupStrategy, ArchiveCleanupStrategy
├── Worker/           OutboxProcessor (core processing loop)
├── Events/           OutboxEventStored, OutboxEventProcessed, OutboxEventFailed
├── Jobs/             OutboxEventJob
├── Commands/         outbox:work, outbox:cleanup, outbox:status
├── Facades/          Outbox
├── DefaultOutboxManager.php
└── OutboxServiceProvider.php
config/outbox.php
database/migrations/create_outbox_events_table.php
```

## Code Standards

- PSR-12 code style
- NO `declare(strict_types=1)` — Laravel convention
- Type declarations on all parameters, return types, properties
- Interfaces in `Contracts/` — implementations depend on contracts, not each other
- Config accessed via `config('outbox.*')` at runtime, not in constructors for model/migration
- Eloquent model uses `getTable()` / `getConnectionName()` methods (not properties) for config compatibility

## Commands

```bash
vendor/bin/phpunit            # Run all tests
php artisan outbox:work       # Start worker daemon
php artisan outbox:work --once  # Single batch run
php artisan outbox:cleanup    # Clean old processed events
php artisan outbox:status     # Show statistics
```

## Testing

- PHPUnit + Orchestra Testbench
- SQLite in-memory for tests (no SKIP LOCKED — uses fallback FOR UPDATE)
- Tests in `tests/Unit/` and `tests/Feature/`
- Transport mocked via Mockery in processor tests
- RefreshDatabase trait for DB isolation

## Key Technical Notes

- `EloquentOutboxStorage::fetchAndLock()` uses `try/catch` for SKIP LOCKED — SQLite doesn't support it, falls back to regular FOR UPDATE
- After in-memory attribute updates, `syncOriginal()` is called to prevent dirty-check issues with subsequent `update()` calls
- Worker generates unique `workerId` per batch via `Str::uuid()`
- Stale locks released at the start of each processing cycle (events stuck in `processing` longer than `lock_timeout`)
- Retry: `status` returns to `pending` with `next_retry_at` set; terminal failure keeps `status = failed`

## CI/CD

GitHub Actions matrix: PHP 8.1-8.4 x Laravel 10-12 (excluding incompatible: L11/L12 require PHP 8.2+). Plus `prefer-lowest` job.
