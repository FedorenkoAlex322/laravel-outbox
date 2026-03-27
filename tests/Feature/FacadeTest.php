<?php

namespace Outbox\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Outbox\DTOs\OutboxEventDTO;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Facades\Outbox;
use Outbox\Models\OutboxEvent;
use Outbox\Tests\TestCase;

class FacadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_store_works_via_facade(): void
    {
        $dto = OutboxEventDTO::make(
            type: 'order.created',
            payload: ['order_id' => 1],
        );

        $event = Outbox::store($dto);

        $this->assertInstanceOf(OutboxEvent::class, $event);
        $this->assertSame('order.created', $event->type);
        $this->assertSame(OutboxEventStatus::Pending, $event->status);
        $this->assertDatabaseHas('outbox_events', ['id' => $event->id]);
    }

    public function test_outbox_get_stats_works_via_facade(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => [], 'status' => OutboxEventStatus::Failed]);

        $stats = Outbox::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('processed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['failed']);
    }
}
