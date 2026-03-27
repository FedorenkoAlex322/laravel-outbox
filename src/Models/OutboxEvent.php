<?php

namespace Outbox\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Outbox\Enums\OutboxEventStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property string $type
 * @property string|null $idempotency_key
 * @property string|null $locked_by
 * @property string|null $last_error
 * @property array $payload
 * @property array|null $metadata
 * @property OutboxEventStatus $status
 * @property int $attempts
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $locked_until
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> pending()
 * @method static Builder<static> failed()
 * @method static Builder<static> processed()
 * @method static Builder<static> readyForRetry()
 * @method static Builder<static> olderThan(DateTimeInterface $date)
 * @method static Builder<static> staleProcessing(DateTimeInterface $threshold)
 */
class OutboxEvent extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('outbox.table', 'outbox_events');
    }

    public function getConnectionName(): ?string
    {
        return config('outbox.connection') ?? parent::getConnectionName();
    }

    protected $casts = [
        'status' => OutboxEventStatus::class,
        'payload' => 'array',
        'metadata' => 'array',
        'next_retry_at' => 'datetime',
        'locked_until' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OutboxEventStatus::Pending);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', OutboxEventStatus::Failed);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', OutboxEventStatus::Processed);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeReadyForRetry(Builder $query): Builder
    {
        return $query
            ->where('status', OutboxEventStatus::Failed)
            ->where('next_retry_at', '<=', now())
            ->where('attempts', '<', config('outbox.retry.max_attempts', 5));
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeOlderThan(Builder $query, DateTimeInterface $date): Builder
    {
        return $query->where('created_at', '<', $date);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeStaleProcessing(Builder $query, DateTimeInterface $threshold): Builder
    {
        return $query
            ->where('status', OutboxEventStatus::Processing)
            ->where('locked_until', '<', $threshold);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === OutboxEventStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === OutboxEventStatus::Processing;
    }

    public function isProcessed(): bool
    {
        return $this->status === OutboxEventStatus::Processed;
    }

    public function isFailed(): bool
    {
        return $this->status === OutboxEventStatus::Failed;
    }
}
