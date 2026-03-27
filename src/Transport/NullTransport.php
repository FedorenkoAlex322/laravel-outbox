<?php

namespace Outbox\Transport;

use Illuminate\Support\Facades\Log;
use Outbox\Contracts\Transport;
use Outbox\Models\OutboxEvent;

class NullTransport implements Transport
{
    public function send(OutboxEvent $event): void
    {
        Log::debug('Outbox NullTransport: event sent', [
            'uuid' => $event->uuid,
            'type' => $event->type,
        ]);
    }

    public function getName(): string
    {
        return 'null';
    }
}
