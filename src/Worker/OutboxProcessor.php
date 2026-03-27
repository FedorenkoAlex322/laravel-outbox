<?php

namespace Outbox\Worker;

use Outbox\Contracts\OutboxStorage;
use Outbox\Contracts\RetryStrategy;
use Outbox\Contracts\Transport;
use Outbox\Events\OutboxEventFailed;
use Outbox\Events\OutboxEventProcessed;
use Illuminate\Support\Str;

class OutboxProcessor
{
    public function __construct(
        protected readonly OutboxStorage $storage,
        protected readonly Transport $transport,
        protected readonly RetryStrategy $retryStrategy,
    ) {}

    /**
     * Process a batch of pending outbox events.
     * Returns the number of successfully processed events.
     */
    public function process(int $batchSize): int
    {
        $workerId = Str::uuid()->toString();

        // Release stale locks first
        $this->storage->releaseStaleLocks(now()->subSeconds(
            (int) config('outbox.worker.lock_timeout', 300)
        ));

        // Fetch and lock events
        $events = $this->storage->fetchAndLock($batchSize, $workerId);

        if ($events->isEmpty()) {
            return 0;
        }

        $processed = 0;

        foreach ($events as $event) {
            try {
                $this->transport->send($event);
                $this->storage->markAsProcessed($event);

                if (config('outbox.events.enabled', true)) {
                    event(new OutboxEventProcessed($event));
                }

                $processed++;
            } catch (\Throwable $e) {
                $willRetry = $this->retryStrategy->shouldRetry($event->attempts + 1);
                $this->storage->markAsFailed($event, $e->getMessage());

                if (config('outbox.events.enabled', true)) {
                    event(new OutboxEventFailed($event, $e->getMessage(), $willRetry));
                }
            }
        }

        return $processed;
    }
}
