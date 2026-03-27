# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-27

### Added
- Outbox Pattern implementation with transactional event storage via `OutboxManager` and `OutboxEventDTO`.
- Polling-based worker (`outbox:work`) with configurable batch size, poll interval, memory limit, and graceful shutdown (SIGINT/SIGTERM).
- `EloquentOutboxStorage` with `SELECT FOR UPDATE SKIP LOCKED` for concurrent worker safety (MySQL 8+, PostgreSQL 9.5+), with automatic fallback for SQLite.
- `LaravelQueueTransport` dispatching outbox events as Laravel Queue jobs with configurable connection and queue name.
- `NullTransport` for testing and development environments.
- `ExponentialBackoffStrategy` with configurable base delay, multiplier, max delay, and 0-25% random jitter.
- `DatabaseLockManager` and `RedisLockManager` for distributed locking across multiple workers.
- `DeleteCleanupStrategy` for permanent removal of old processed events.
- `ArchiveCleanupStrategy` for moving old events to a separate archive table.
- Artisan commands: `outbox:work`, `outbox:cleanup`, `outbox:status`.
- Laravel events: `OutboxEventStored`, `OutboxEventProcessed`, `OutboxEventFailed`.
- Stale lock detection and automatic release for crashed workers.
- Idempotency key support for consumer-side deduplication.
- Full configuration via `config/outbox.php` with environment variable overrides.
- Auto-discovery via Laravel package discovery (no manual service provider registration).
- Support for PHP 8.1-8.4 and Laravel 10, 11, 12.
