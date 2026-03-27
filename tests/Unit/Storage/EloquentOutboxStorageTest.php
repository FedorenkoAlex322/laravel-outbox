<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Unit\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use FedorenkoAlex322\LaravelOutbox\Contracts\RetryStrategy;
use FedorenkoAlex322\LaravelOutbox\DTOs\OutboxEventDTO;
use FedorenkoAlex322\LaravelOutbox\Enums\OutboxEventStatus;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;
use FedorenkoAlex322\LaravelOutbox\Storage\EloquentOutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;

class EloquentOutboxStorageTest extends TestCase
{
    use RefreshDatabase;

    protected EloquentOutboxStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = $this->app->make(EloquentOutboxStorage::class);
    }

    public function test_store_creates_record_in_database_with_correct_data(): void
    {
        $dto = new OutboxEventDTO(
            type: 'order.created',
            payload: ['order_id' => 42, 'total' => 100.50],
            metadata: ['source' => 'api'],
        );

        $event = $this->storage->store($dto);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'type' => 'order.created',
            'status' => 'pending',
        ]);

        $this->assertSame('order.created', $event->type);
        $this->assertSame(['order_id' => 42, 'total' => 100.50], $event->payload);
        $this->assertSame(['source' => 'api'], $event->metadata);
        $this->assertSame(OutboxEventStatus::Pending, $event->status);
        $this->assertNotNull($event->uuid);
    }

    public function test_store_with_idempotency_key(): void
    {
        $dto = new OutboxEventDTO(
            type: 'order.created',
            payload: ['order_id' => 1],
            idempotencyKey: 'idem-key-123',
        );

        $event = $this->storage->store($dto);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'idempotency_key' => 'idem-key-123',
        ]);

        $this->assertSame('idem-key-123', $event->idempotency_key);
    }

    public function test_fetch_and_lock_returns_pending_events(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => ['id' => 1], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => ['id' => 2], 'status' => OutboxEventStatus::Pending]);

        $events = $this->storage->fetchAndLock(10, 'worker-1');

        $this->assertCount(2, $events);
    }

    public function test_fetch_and_lock_sets_status_processing_and_locked_by(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => ['id' => 1], 'status' => OutboxEventStatus::Pending]);

        $events = $this->storage->fetchAndLock(10, 'worker-1');

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame(OutboxEventStatus::Processing, $event->status);
        $this->assertSame('worker-1', $event->locked_by);
        $this->assertNotNull($event->locked_until);

        // Verify in DB as well
        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => 'processing',
            'locked_by' => 'worker-1',
        ]);
    }

    public function test_fetch_and_lock_does_not_return_already_locked_events(): void
    {
        // Create an event that is already locked (processing with future locked_until)
        OutboxEvent::create([
            'type' => 'locked',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_by' => 'other-worker',
            'locked_until' => now()->addMinutes(5),
        ]);

        $events = $this->storage->fetchAndLock(10, 'worker-2');

        $this->assertCount(0, $events);
    }

    public function test_fetch_and_lock_returns_failed_events_ready_for_retry(): void
    {
        OutboxEvent::create([
            'type' => 'retryable',
            'payload' => [],
            'status' => OutboxEventStatus::Failed,
            'attempts' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $events = $this->storage->fetchAndLock(10, 'worker-1');

        $this->assertCount(1, $events);
        $this->assertSame('retryable', $events->first()->type);
    }

    public function test_mark_as_processed_updates_status_and_processed_at(): void
    {
        $event = OutboxEvent::create([
            'type' => 'a',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_by' => 'worker-1',
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->storage->markAsProcessed($event);

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Processed, $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->locked_by);
        $this->assertNull($event->locked_until);
    }

    public function test_mark_as_failed_with_retry_increments_attempts_and_sets_next_retry(): void
    {
        $event = OutboxEvent::create([
            'type' => 'a',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'attempts' => 0,
            'locked_by' => 'worker-1',
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->storage->markAsFailed($event, 'Connection timeout');

        $event->refresh();
        // attempts=1 < max_attempts=5, so should retry
        $this->assertSame(1, $event->attempts);
        $this->assertSame(OutboxEventStatus::Pending, $event->status);
        $this->assertNotNull($event->next_retry_at);
        $this->assertSame('Connection timeout', $event->last_error);
        $this->assertNull($event->locked_by);
        $this->assertNull($event->locked_until);
    }

    public function test_mark_as_failed_without_retry_sets_terminal_failed_status(): void
    {
        $event = OutboxEvent::create([
            'type' => 'a',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'attempts' => 4, // next will be 5, which equals max
            'locked_by' => 'worker-1',
            'locked_until' => now()->addMinutes(5),
        ]);

        $this->storage->markAsFailed($event, 'Final failure');

        $event->refresh();
        $this->assertSame(5, $event->attempts);
        $this->assertSame(OutboxEventStatus::Failed, $event->status);
        $this->assertSame('Final failure', $event->last_error);
        $this->assertNull($event->locked_by);
    }

    public function test_release_stale_locks_updates_stale_events(): void
    {
        // Stale event: processing with expired lock
        OutboxEvent::create([
            'type' => 'stale',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_by' => 'dead-worker',
            'locked_until' => now()->subMinutes(10),
        ]);

        // Active event: processing with valid lock
        OutboxEvent::create([
            'type' => 'active',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_by' => 'worker-1',
            'locked_until' => now()->addMinutes(5),
        ]);

        $released = $this->storage->releaseStaleLocks(now());

        $this->assertSame(1, $released);

        $this->assertDatabaseHas('outbox_events', [
            'type' => 'stale',
            'status' => 'pending',
            'locked_by' => null,
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'type' => 'active',
            'status' => 'processing',
            'locked_by' => 'worker-1',
        ]);
    }

    public function test_get_stats_returns_counts_by_status(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'c', 'payload' => [], 'status' => OutboxEventStatus::Processing]);
        OutboxEvent::create(['type' => 'd', 'payload' => [], 'status' => OutboxEventStatus::Processed, 'processed_at' => now()]);
        OutboxEvent::create(['type' => 'e', 'payload' => [], 'status' => OutboxEventStatus::Failed]);

        $stats = $this->storage->getStats();

        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['processing']);
        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['failed']);
        $this->assertArrayHasKey('oldest_pending_minutes', $stats);
        $this->assertNotNull($stats['oldest_pending_minutes']);
    }
}
