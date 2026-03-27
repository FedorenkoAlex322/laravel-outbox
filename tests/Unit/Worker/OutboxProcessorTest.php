<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Unit\Worker;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Contracts\RetryStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\Transport;
use FedorenkoAlex322\LaravelOutbox\Enums\OutboxEventStatus;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;
use FedorenkoAlex322\LaravelOutbox\Worker\OutboxProcessor;

class OutboxProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_with_empty_queue_returns_zero(): void
    {
        $storage = Mockery::mock(OutboxStorage::class);
        $transport = Mockery::mock(Transport::class);
        $retryStrategy = Mockery::mock(RetryStrategy::class);

        $storage->shouldReceive('releaseStaleLocks')->once();
        $storage->shouldReceive('fetchAndLock')->once()->andReturn(new Collection());

        $processor = new OutboxProcessor($storage, $transport, $retryStrategy);

        $result = $processor->process(10);

        $this->assertSame(0, $result);
    }

    public function test_process_sends_events_through_transport(): void
    {
        $event1 = new OutboxEvent([
            'uuid' => 'uuid-1',
            'type' => 'order.created',
            'payload' => ['id' => 1],
            'status' => OutboxEventStatus::Processing,
            'attempts' => 0,
        ]);
        $event2 = new OutboxEvent([
            'uuid' => 'uuid-2',
            'type' => 'order.updated',
            'payload' => ['id' => 2],
            'status' => OutboxEventStatus::Processing,
            'attempts' => 0,
        ]);

        $storage = Mockery::mock(OutboxStorage::class);
        $transport = Mockery::mock(Transport::class);
        $retryStrategy = Mockery::mock(RetryStrategy::class);

        $storage->shouldReceive('releaseStaleLocks')->once();
        $storage->shouldReceive('fetchAndLock')->once()->andReturn(new Collection([$event1, $event2]));
        $storage->shouldReceive('markAsProcessed')->twice();

        $transport->shouldReceive('send')->with($event1)->once();
        $transport->shouldReceive('send')->with($event2)->once();

        $processor = new OutboxProcessor($storage, $transport, $retryStrategy);

        $result = $processor->process(10);

        $this->assertSame(2, $result);
    }

    public function test_process_calls_mark_as_failed_on_transport_error(): void
    {
        $event = new OutboxEvent([
            'uuid' => 'uuid-1',
            'type' => 'order.created',
            'payload' => ['id' => 1],
            'status' => OutboxEventStatus::Processing,
            'attempts' => 0,
        ]);

        $storage = Mockery::mock(OutboxStorage::class);
        $transport = Mockery::mock(Transport::class);
        $retryStrategy = Mockery::mock(RetryStrategy::class);

        $storage->shouldReceive('releaseStaleLocks')->once();
        $storage->shouldReceive('fetchAndLock')->once()->andReturn(new Collection([$event]));
        $storage->shouldReceive('markAsFailed')->once()->with($event, 'Transport error');

        $transport->shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport error'));

        $retryStrategy->shouldReceive('shouldRetry')->once()->with(1)->andReturn(true);

        $processor = new OutboxProcessor($storage, $transport, $retryStrategy);

        $result = $processor->process(10);

        $this->assertSame(0, $result);
    }
}
