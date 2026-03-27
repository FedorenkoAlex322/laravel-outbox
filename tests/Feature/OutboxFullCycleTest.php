<?php

namespace Outbox\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Outbox\Contracts\OutboxManager;
use Outbox\Contracts\Transport;
use Outbox\DTOs\OutboxEventDTO;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Models\OutboxEvent;
use Outbox\Tests\TestCase;
use Outbox\Transport\NullTransport;

class OutboxFullCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('outbox.transport.driver', 'null');
    }

    public function test_store_event_then_process_and_verify_processed_in_database(): void
    {
        $manager = $this->app->make(OutboxManager::class);

        $dto = OutboxEventDTO::make(
            type: 'order.created',
            payload: ['order_id' => 42],
            metadata: ['source' => 'test'],
        );

        $event = $manager->store($dto);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => 'pending',
        ]);

        $processed = $manager->process(10);

        $this->assertSame(1, $processed);

        $event->refresh();
        $this->assertSame(OutboxEventStatus::Processed, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_store_event_transport_fails_and_verify_retry_scheduling(): void
    {
        $storage = $this->app->make(\Outbox\Contracts\OutboxStorage::class);

        $dto = OutboxEventDTO::make(
            type: 'order.created',
            payload: ['order_id' => 42],
        );

        $storedEvent = $storage->store($dto);

        // Simulate the state after fetchAndLock: manually set the event to processing
        // and refresh the model so originals are synced (as would happen with a fresh load).
        $storedEvent->update([
            'status' => OutboxEventStatus::Processing,
            'locked_by' => 'worker-test',
            'locked_until' => now()->addMinutes(5),
        ]);
        $storedEvent->refresh();

        // Simulate: transport fails, so markAsFailed is called
        $storage->markAsFailed($storedEvent, 'Connection refused');

        // Verify in DB: should be set back to pending for retry (attempts=1 < max=5)
        $dbEvent = OutboxEvent::query()->find($storedEvent->id);
        $this->assertSame(OutboxEventStatus::Pending, $dbEvent->status);
        $this->assertSame(1, $dbEvent->attempts);
        $this->assertNotNull($dbEvent->next_retry_at);
        $this->assertSame('Connection refused', $dbEvent->last_error);
        $this->assertNull($dbEvent->locked_by);
    }

    public function test_store_multiple_events_process_batch_and_all_processed(): void
    {
        $manager = $this->app->make(OutboxManager::class);

        $events = [];
        for ($i = 1; $i <= 5; $i++) {
            $events[] = $manager->store(
                OutboxEventDTO::make(
                    type: 'order.created',
                    payload: ['order_id' => $i],
                )
            );
        }

        $processed = $manager->process(10);

        $this->assertSame(5, $processed);

        foreach ($events as $event) {
            $event->refresh();
            $this->assertSame(OutboxEventStatus::Processed, $event->status);
        }
    }
}
