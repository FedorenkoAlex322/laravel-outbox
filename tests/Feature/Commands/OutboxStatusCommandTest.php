<?php

namespace Outbox\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Models\OutboxEvent;
use Outbox\Tests\TestCase;

class OutboxStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_displays_statistics_table(): void
    {
        OutboxEvent::create(['type' => 'a', 'payload' => [], 'status' => OutboxEventStatus::Pending]);
        OutboxEvent::create(['type' => 'b', 'payload' => [], 'status' => OutboxEventStatus::Processed, 'processed_at' => now()]);
        OutboxEvent::create(['type' => 'c', 'payload' => [], 'status' => OutboxEventStatus::Failed]);

        $this->artisan('outbox:status')
            ->assertSuccessful();

        // Verify the underlying stats are correct
        $manager = $this->app->make(\Outbox\Contracts\OutboxManager::class);
        $stats = $manager->getStats();

        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['processing']);
        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['failed']);
    }
}
