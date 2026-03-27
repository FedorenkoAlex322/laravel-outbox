<?php

namespace Outbox\Storage;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Outbox\Contracts\OutboxEventData;
use Outbox\Contracts\OutboxStorage;
use Outbox\Contracts\RetryStrategy;
use Outbox\Enums\OutboxEventStatus;
use Outbox\Models\OutboxEvent;

class EloquentOutboxStorage implements OutboxStorage
{
    public function __construct(
        protected readonly RetryStrategy $retryStrategy,
    ) {}

    public function store(OutboxEventData $event): OutboxEvent
    {
        return OutboxEvent::create([
            'type' => $event->getType(),
            'payload' => $event->getPayload(),
            'metadata' => $event->getMetadata(),
            'idempotency_key' => $event->getIdempotencyKey(),
            'status' => OutboxEventStatus::Pending,
        ]);
    }

    /**
     * @return Collection<int, OutboxEvent>
     */
    public function fetchAndLock(int $batchSize, string $workerId): Collection
    {
        $lockTimeout = (int) config('outbox.worker.lock_timeout', 300);
        $maxAttempts = $this->retryStrategy->getMaxAttempts();
        $now = now();

        return DB::connection($this->getConnection())
            ->transaction(function () use ($batchSize, $workerId, $lockTimeout, $maxAttempts, $now): Collection {
                $query = OutboxEvent::query()
                    ->where(function ($q) use ($now, $maxAttempts): void {
                        $q->where('status', OutboxEventStatus::Pending->value)
                            ->orWhere(function ($q2) use ($now, $maxAttempts): void {
                                $q2->where('status', OutboxEventStatus::Failed->value)
                                    ->where('next_retry_at', '<=', $now)
                                    ->where('attempts', '<', $maxAttempts);
                            });
                    })
                    ->where(function ($q) use ($now): void {
                        $q->whereNull('locked_until')
                            ->orWhere('locked_until', '<', $now);
                    })
                    ->orderBy('created_at')
                    ->limit($batchSize);

                // Try SKIP LOCKED first (MySQL 8+, PostgreSQL 9.5+),
                // fall back to regular FOR UPDATE for SQLite and older databases.
                try {
                    $events = (clone $query)->lock('FOR UPDATE SKIP LOCKED')->get();
                } catch (\Illuminate\Database\QueryException) {
                    $events = $query->lockForUpdate()->get();
                }

                if ($events->isEmpty()) {
                    return $events;
                }

                $lockedUntil = $now->copy()->addSeconds($lockTimeout);

                OutboxEvent::whereIn('id', $events->pluck('id'))
                    ->update([
                        'status' => OutboxEventStatus::Processing->value,
                        'locked_by' => $workerId,
                        'locked_until' => $lockedUntil,
                    ]);

                // Refresh models to reflect the updated state.
                $events->each(function (OutboxEvent $event) use ($workerId, $lockedUntil): void {
                    $event->status = OutboxEventStatus::Processing;
                    $event->locked_by = $workerId;
                    $event->locked_until = $lockedUntil;
                    $event->syncOriginal();
                });

                return $events;
            });
    }

    public function markAsProcessed(OutboxEvent $event): void
    {
        $event->update([
            'status' => OutboxEventStatus::Processed,
            'processed_at' => now(),
            'locked_by' => null,
            'locked_until' => null,
        ]);
    }

    public function markAsFailed(OutboxEvent $event, string $error): void
    {
        $attempts = $event->attempts + 1;
        $shouldRetry = $this->retryStrategy->shouldRetry($attempts);

        $data = [
            'attempts' => $attempts,
            'last_error' => mb_substr($error, 0, 65535),
            'locked_by' => null,
            'locked_until' => null,
        ];

        if ($shouldRetry) {
            $delay = $this->retryStrategy->getDelay($attempts);
            $data['status'] = OutboxEventStatus::Pending;
            $data['next_retry_at'] = now()->addSeconds($delay);
        } else {
            $data['status'] = OutboxEventStatus::Failed;
        }

        $event->update($data);
    }

    public function releaseStaleLocks(DateTimeInterface $threshold): int
    {
        return OutboxEvent::query()
            ->staleProcessing($threshold)
            ->update([
                'status' => OutboxEventStatus::Pending->value,
                'locked_by' => null,
                'locked_until' => null,
            ]);
    }

    /**
     * @return array{pending: int, processing: int, processed: int, failed: int, oldest_pending_minutes: float|null}
     */
    public function getStats(): array
    {
        $counts = OutboxEvent::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $oldestPending = OutboxEvent::query()
            ->where('status', OutboxEventStatus::Pending->value)
            ->min('created_at');

        $oldestPendingMinutes = $oldestPending !== null
            ? now()->diffInSeconds($oldestPending) / 60
            : null;

        return [
            'pending' => (int) ($counts[OutboxEventStatus::Pending->value] ?? 0),
            'processing' => (int) ($counts[OutboxEventStatus::Processing->value] ?? 0),
            'processed' => (int) ($counts[OutboxEventStatus::Processed->value] ?? 0),
            'failed' => (int) ($counts[OutboxEventStatus::Failed->value] ?? 0),
            'oldest_pending_minutes' => $oldestPendingMinutes !== null
                ? round($oldestPendingMinutes, 2)
                : null,
        ];
    }

    protected function getConnection(): ?string
    {
        return config('outbox.connection');
    }
}
