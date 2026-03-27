<?php

namespace Outbox\Transport;

use Outbox\Contracts\Transport;
use Outbox\Jobs\OutboxEventJob;
use Outbox\Models\OutboxEvent;

class LaravelQueueTransport implements Transport
{
    public function send(OutboxEvent $event): void
    {
        $connection = config('outbox.transport.queue.connection');
        $queue = config('outbox.transport.queue.queue', 'outbox');

        $job = new OutboxEventJob(
            uuid: $event->uuid,
            type: $event->type,
            payload: $event->payload,
            metadata: $event->metadata ?? [],
        );

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    public function getName(): string
    {
        return 'queue';
    }
}
