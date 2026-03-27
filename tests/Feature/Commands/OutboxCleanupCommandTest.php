<?php

namespace Outbox\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Models\OutboxEvent;
use Outbox\Tests\TestCase;

class OutboxCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_old_processed_events(): void
    {
        // Old processed event (older than retention period of 7 days)
        OutboxEvent::create([
            'type' => 'old.event',
            'payload' => [],
            'status' => OutboxEventStatus::Processed,
            'processed_at' => now()->subDays(10),
        ]);

        $this->artisan('outbox:cleanup')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up 1 event(s)');

        $this->assertDatabaseMissing('outbox_events', ['type' => 'old.event']);
    }

    public function test_cleanup_does_not_delete_fresh_processed_events(): void
    {
        // Fresh processed event (within retention period)
        OutboxEvent::create([
            'type' => 'fresh.event',
            'payload' => [],
            'status' => OutboxEventStatus::Processed,
            'processed_at' => now()->subDays(2),
        ]);

        $this->artisan('outbox:cleanup')
            ->assertSuccessful()
            ->expectsOutputToContain('Cleaned up 0 event(s)');

        $this->assertDatabaseHas('outbox_events', ['type' => 'fresh.event']);
    }
}
