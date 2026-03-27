<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use FedorenkoAlex322\LaravelOutbox\DTOs\OutboxEventDTO;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;

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
