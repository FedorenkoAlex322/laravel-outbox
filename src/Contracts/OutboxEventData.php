<?php

namespace Outbox\Contracts;

interface OutboxEventData
{
    public function getType(): string;

    public function getPayload(): array;

    public function getMetadata(): array;

    public function getIdempotencyKey(): ?string;
}
