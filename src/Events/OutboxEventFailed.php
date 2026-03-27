<?php

namespace Outbox\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Outbox\Models\OutboxEvent;

class OutboxEventFailed
{
    use Dispatchable;

    public function __construct(
        public readonly OutboxEvent $outboxEvent,
        public readonly string $error,
        public readonly bool $willRetry,
    ) {}
}
