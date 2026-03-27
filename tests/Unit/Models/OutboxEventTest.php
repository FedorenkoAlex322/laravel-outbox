<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use FedorenkoAlex322\LaravelOutbox\Enums\OutboxEventStatus;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;

class OutboxEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_is_generated_automatically_on_creation(): void
    {
        $event = OutboxEvent::create([
            'type' => 'order.created',
            'payload' => ['order_id' => 1],
            'status' => OutboxEventStatus::Pending,
        ]);

        $this->assertNotNull($event->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $event->uuid,
        );
    }

    public function test_scope_pending_filters_correctly(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => [], 'status' => OutboxEventStatus::Processing]);
        OutboxEvent::create(['type' => 'c', 'payload' => [], 'status' => OutboxEventStatus::Processed]);
        OutboxEvent::create(['type' => 'd', 'payload' => [], 'status' => OutboxEventStatus::Failed]);

        $pending = OutboxEvent::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertSame('a', $pending->first()->type);
    }

    public function test_scope_failed_filters_correctly(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => [], 'status' => OutboxEventStatus::Failed]);

        $failed = OutboxEvent::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertSame('b', $failed->first()->type);
    }

    public function test_scope_ready_for_retry_selects_failed_with_valid_retry_conditions(): void
    {
        // Should be included: failed, next_retry_at in past, attempts < max
        OutboxEvent::create([
            'type' => 'retryable',
            'payload' => [],
            'status' => OutboxEventStatus::Failed,
            'next_retry_at' => now()->subMinute(),
            'attempts' => 2,
        ]);

        // Should be excluded: next_retry_at in future
        OutboxEvent::create([
            'type' => 'not-yet',
            'payload' => [],
            'status' => OutboxEventStatus::Failed,
            'next_retry_at' => now()->addHour(),
            'attempts' => 1,
        ]);

        // Should be excluded: attempts >= max (default 5)
        OutboxEvent::create([
            'type' => 'exhausted',
            'payload' => [],
            'status' => OutboxEventStatus::Failed,
            'next_retry_at' => now()->subMinute(),
            'attempts' => 5,
        ]);

        // Should be excluded: pending status
        OutboxEvent::create([
            'type' => 'pending',
            'payload' => [],
            'status' => OutboxEventStatus::Pending,
        ]);

        $readyForRetry = OutboxEvent::readyForRetry()->get();

        $this->assertCount(1, $readyForRetry);
        $this->assertSame('retryable', $readyForRetry->first()->type);
    }

    public function test_scope_stale_processing_selects_processing_with_expired_lock(): void
    {
        $threshold = now();

        // Should be included: processing, locked_until before threshold
        OutboxEvent::create([
            'type' => 'stale',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_until' => now()->subMinutes(10),
        ]);

        // Should be excluded: processing, locked_until after threshold
        OutboxEvent::create([
            'type' => 'active',
            'payload' => [],
            'status' => OutboxEventStatus::Processing,
            'locked_until' => now()->addMinutes(10),
        ]);

        // Should be excluded: pending status
        OutboxEvent::create([
            'type' => 'pending',
            'payload' => [],
            'status' => OutboxEventStatus::Pending,
        ]);

        $stale = OutboxEvent::staleProcessing($threshold)->get();

        $this->assertCount(1, $stale);
        $this->assertSame('stale', $stale->first()->type);
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        $event = new OutboxEvent();
        $event->status = OutboxEventStatus::Pending;

        $this->assertTrue($event->isPending());
        $this->assertFalse($event->isProcessing());
        $this->assertFalse($event->isProcessed());
        $this->assertFalse($event->isFailed());
    }

    public function test_is_processing_returns_true_for_processing_status(): void
    {
        $event = new OutboxEvent();
        $event->status = OutboxEventStatus::Processing;

        $this->assertFalse($event->isPending());
        $this->assertTrue($event->isProcessing());
        $this->assertFalse($event->isProcessed());
        $this->assertFalse($event->isFailed());
    }

    public function test_is_processed_returns_true_for_processed_status(): void
    {
        $event = new OutboxEvent();
        $event->status = OutboxEventStatus::Processed;

        $this->assertFalse($event->isPending());
        $this->assertFalse($event->isProcessing());
        $this->assertTrue($event->isProcessed());
        $this->assertFalse($event->isFailed());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $event = new OutboxEvent();
        $event->status = OutboxEventStatus::Failed;

        $this->assertFalse($event->isPending());
        $this->assertFalse($event->isProcessing());
        $this->assertFalse($event->isProcessed());
        $this->assertTrue($event->isFailed());
    }
}
