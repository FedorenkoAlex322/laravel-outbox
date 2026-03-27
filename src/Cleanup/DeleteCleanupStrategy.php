<?php

namespace FedorenkoAlex322\LaravelOutbox\Cleanup;

use FedorenkoAlex322\LaravelOutbox\Contracts\CleanupStrategy;
use FedorenkoAlex322\LaravelOutbox\Enums\OutboxEventStatus;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

class DeleteCleanupStrategy implements CleanupStrategy
{
    public function cleanup(int $retentionDays, int $batchSize): int
    {
        $threshold = now()->subDays($retentionDays);
        $totalDeleted = 0;

        do {
            $ids = OutboxEvent::query()
                ->where('status', OutboxEventStatus::Processed->value)
                ->where('processed_at', '<', $threshold)
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = OutboxEvent::whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;
        } while ($deleted >= $batchSize);

        return $totalDeleted;
    }
}
