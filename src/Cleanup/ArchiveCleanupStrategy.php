<?php

namespace Outbox\Cleanup;

use Illuminate\Support\Facades\DB;
use Outbox\Contracts\CleanupStrategy;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Models\OutboxEvent;

class ArchiveCleanupStrategy implements CleanupStrategy
{
    public function cleanup(int $retentionDays, int $batchSize): int
    {
        $threshold = now()->subDays($retentionDays);
        $sourceTable = config('outbox.table', 'outbox_events');
        $archiveTable = config('outbox.cleanup.archive_table', 'outbox_events_archive');
        $totalArchived = 0;
        $connection = config('outbox.connection');

        do {
            $archived = 0;

            DB::connection($connection)->transaction(function () use (
                $sourceTable, $archiveTable, $threshold, $batchSize, &$archived
            ) {
                $events = OutboxEvent::query()
                    ->where('status', OutboxEventStatus::Processed->value)
                    ->where('processed_at', '<', $threshold)
                    ->limit($batchSize)
                    ->get();

                if ($events->isEmpty()) {
                    return;
                }

                $archived = $events->count();

                // Insert into archive table
                $records = $events->map(fn ($e) => $e->getAttributes())->toArray();
                DB::connection($connection)->table($archiveTable)->insert($records);

                // Delete from source
                OutboxEvent::query()
                    ->whereIn('id', $events->pluck('id'))
                    ->delete();
            });

            $totalArchived += $archived;
        } while ($archived >= $batchSize);

        return $totalArchived;
    }
}
