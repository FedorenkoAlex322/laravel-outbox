<?php

namespace FedorenkoAlex322\LaravelOutbox\Transport;

use Illuminate\Support\Facades\Log;
use FedorenkoAlex322\LaravelOutbox\Contracts\Transport;
use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

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
