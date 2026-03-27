<?php

namespace FedorenkoAlex322\LaravelOutbox\Contracts;

use Illuminate\Support\Collection;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

interface OutboxStorage
{
    /**
     * Store an event in the outbox. Must be called within a DB transaction.
     */
    public function store(OutboxEventData $event): OutboxEvent;

    /**
     * Fetch and lock a batch of pending events for processing.
     * Uses SELECT FOR UPDATE SKIP LOCKED for concurrency safety.
     *
     * @return Collection<int, OutboxEvent>
     */
    public function fetchAndLock(int $batchSize, string $workerId): Collection;

    /**
     * Mark event as successfully processed.
     */
    public function markAsProcessed(OutboxEvent $event): void;

    /**
     * Mark event as failed with error message.
     */
    public function markAsFailed(OutboxEvent $event, string $error): void;

    /**
     * Release stale locks older than threshold.
     */
    public function releaseStaleLocks(\DateTimeInterface $threshold): int;

    /**
     * Get outbox statistics (counts by status, oldest pending age).
     */
    public function getStats(): array;
}
