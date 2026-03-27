<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbox Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the database table used to store outbox events.
    |
    */
    'table' => env('OUTBOX_TABLE', 'outbox_events'),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection used for outbox events.
    | Set to null to use the default connection.
    |
    */
    'connection' => env('OUTBOX_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Transport Configuration
    |--------------------------------------------------------------------------
    |
    | The transport is responsible for delivering outbox events to external
    | systems. The 'queue' driver dispatches events as Laravel Queue jobs.
    | You may also implement your own transport by providing a class name.
    |
    | Supported drivers: "queue", "null"
    |
    */
    'transport' => [
        'driver' => env('OUTBOX_TRANSPORT', 'queue'),

        'queue' => [
            'connection' => env('OUTBOX_QUEUE_CONNECTION'),
            'queue' => env('OUTBOX_QUEUE_NAME', 'outbox'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of the outbox:work command.
    |
    */
    'worker' => [
        // Number of events to process per batch
        'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

        // Seconds between polling cycles
        'poll_interval' => (float) env('OUTBOX_POLL_INTERVAL', 1.0),

        // Seconds before a processing lock is considered stale
        'lock_timeout' => (int) env('OUTBOX_LOCK_TIMEOUT', 300),

        // Maximum memory usage in MB before worker restarts
        'memory_limit' => (int) env('OUTBOX_MEMORY_LIMIT', 128),

        // Seconds to sleep when no events are available
        'sleep_when_empty' => (int) env('OUTBOX_SLEEP_WHEN_EMPTY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how failed events are retried. The exponential backoff strategy
    | increases the delay between retries: base_delay * (multiplier ^ attempt).
    |
    | Supported strategies: "exponential"
    | You may also provide a class name implementing RetryStrategy contract.
    |
    */
    'retry' => [
        'strategy' => 'exponential',
        'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
        'base_delay' => (int) env('OUTBOX_RETRY_BASE_DELAY', 60),
        'multiplier' => (float) env('OUTBOX_RETRY_MULTIPLIER', 2.0),
        'max_delay' => (int) env('OUTBOX_RETRY_MAX_DELAY', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locking Configuration
    |--------------------------------------------------------------------------
    |
    | Locking prevents multiple workers from processing the same events.
    | The 'database' driver uses SELECT FOR UPDATE SKIP LOCKED (MySQL 8+).
    | The 'redis' driver uses Redis-based distributed locks.
    |
    | Supported drivers: "database", "redis"
    |
    */
    'locking' => [
        'driver' => env('OUTBOX_LOCK_DRIVER', 'database'),

        'redis' => [
            'store' => env('OUTBOX_REDIS_STORE', 'redis'),
            'prefix' => 'outbox:lock:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how old processed events are cleaned up.
    | The 'delete' strategy permanently removes old events.
    | The 'archive' strategy moves them to an archive table.
    |
    | Supported strategies: "delete", "archive"
    |
    */
    'cleanup' => [
        'strategy' => env('OUTBOX_CLEANUP_STRATEGY', 'delete'),
        'retain_days' => (int) env('OUTBOX_RETAIN_DAYS', 7),
        'batch_size' => (int) env('OUTBOX_CLEANUP_BATCH_SIZE', 1000),
        'archive_table' => env('OUTBOX_ARCHIVE_TABLE', 'outbox_events_archive'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | When enabled, Laravel events are dispatched on outbox event status
    | changes. This allows you to hook into the outbox lifecycle for
    | logging, metrics, or custom behavior.
    |
    */
    'events' => [
        'enabled' => true,
    ],

];
