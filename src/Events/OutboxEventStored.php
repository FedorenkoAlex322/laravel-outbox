<?php

namespace Outbox\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Outbox\Models\OutboxEvent;

class OutboxEventStored
{
    use Dispatchable;

    public function __construct(
        public readonly OutboxEvent $outboxEvent,
    ) {}
}
