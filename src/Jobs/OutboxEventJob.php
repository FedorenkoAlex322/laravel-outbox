<?php

namespace Outbox\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly array $payload,
        public readonly array $metadata = [],
    ) {}

    public function handle(): void
    {
        // This job is dispatched by the outbox transport.
        // Override this job or listen for the OutboxEventProcessed event
        // to implement your event handling logic.
    }

    public function tags(): array
    {
        return ['outbox', "outbox:{$this->type}", "outbox:{$this->uuid}"];
    }
}
