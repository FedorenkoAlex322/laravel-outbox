<?php

namespace FedorenkoAlex322\LaravelOutbox\Events;

use Illuminate\Foundation\Events\Dispatchable;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

class OutboxEventFailed
{
    use Dispatchable;

    public function __construct(
        public readonly OutboxEvent $outboxEvent,
        public readonly string $error,
        public readonly bool $willRetry,
    ) {}
}
