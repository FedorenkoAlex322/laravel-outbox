<?php

namespace Outbox\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Outbox\DTOs\OutboxEventDTO;
use Outbox\Contracts\OutboxManager;
use Outbox\Tests\TestCase;

class OutboxWorkerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('outbox.transport.driver', 'null');
    }

    public function test_outbox_work_once_without_events(): void
    {
        $this->artisan('outbox:work', ['--once' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Outbox worker started')
            ->expectsOutputToContain('Outbox worker stopped');
    }

    public function test_outbox_work_once_with_events(): void
    {
        $manager = $this->app->make(OutboxManager::class);

        $manager->store(OutboxEventDTO::make(
            type: 'order.created',
            payload: ['order_id' => 1],
        ));

        $manager->store(OutboxEventDTO::make(
            type: 'order.updated',
            payload: ['order_id' => 2],
        ));

        $this->artisan('outbox:work', ['--once' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 2 event(s)');
    }
}
