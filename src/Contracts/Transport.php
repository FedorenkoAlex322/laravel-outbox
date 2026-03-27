<?php

namespace FedorenkoAlex322\LaravelOutbox\Contracts;

use FedorenkoAlex322\LaravelOutbox\Models\OutboxEvent;

interface Transport
{
    /**
     * Send an outbox event to the external system.
     * Must throw an exception on failure.
     *
     * @throws \Throwable
     */
    public function send(OutboxEvent $event): void;

    /**
     * Get the transport name (for logging/debugging).
     */
    public function getName(): string;
}
