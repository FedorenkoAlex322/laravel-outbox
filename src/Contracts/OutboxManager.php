<?php

namespace FedorenkoAlex322\LaravelOutbox\Contracts;

use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

interface OutboxManager
{
    /**
     * Store an event in the outbox.
     * Must be called within a database transaction to guarantee consistency.
     */
    public function store(OutboxEventData $event): OutboxEvent;

    /**
     * Process a batch of pending outbox events.
     * Returns the number of events processed.
     */
    public function process(int $batchSize = 100): int;

    /**
     * Run cleanup of old processed events.
     * Returns the number of events cleaned up.
     */
    public function cleanup(): int;

    /**
     * Get outbox statistics.
     */
    public function getStats(): array;
}
