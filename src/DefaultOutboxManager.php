<?php

namespace Outbox;

use Outbox\Contracts\CleanupStrategy;
use Outbox\Contracts\OutboxEventData;
use Outbox\Contracts\OutboxManager;
use Outbox\Contracts\OutboxStorage;
use Outbox\Events\OutboxEventStored;
use Outbox\Models\OutboxEvent;
use Outbox\Worker\OutboxProcessor;

class DefaultOutboxManager implements OutboxManager
{
    public function __construct(
        protected readonly OutboxStorage $storage,
        protected readonly OutboxProcessor $processor,
        protected readonly CleanupStrategy $cleanupStrategy,
    ) {}

    public function store(OutboxEventData $event): OutboxEvent
    {
        $outboxEvent = $this->storage->store($event);

        if (config('outbox.events.enabled', true)) {
            event(new OutboxEventStored($outboxEvent));
        }

        return $outboxEvent;
    }

    public function process(int $batchSize = 100): int
    {
        return $this->processor->process($batchSize);
    }

    public function cleanup(): int
    {
        $retentionDays = (int) config('outbox.cleanup.retain_days', 7);
        $batchSize = (int) config('outbox.cleanup.batch_size', 1000);

        return $this->cleanupStrategy->cleanup($retentionDays, $batchSize);
    }

    public function getStats(): array
    {
        return $this->storage->getStats();
    }
}
