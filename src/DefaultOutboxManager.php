<?php

namespace FedorenkoAlex322\LaravelOutbox;

use FedorenkoAlex322\LaravelOutbox\Contracts\CleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxEventData;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxStorage;
use FedorenkoAlex322\LaravelOutbox\Events\OutboxEventStored;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;
use FedorenkoAlex322\LaravelOutbox\Worker\OutboxProcessor;

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
