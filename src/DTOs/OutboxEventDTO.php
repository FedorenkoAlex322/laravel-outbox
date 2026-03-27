<?php

namespace Outbox\DTOs;

use Outbox\Contracts\OutboxEventData;

final class OutboxEventDTO implements OutboxEventData
{
    public function __construct(
        private readonly string $type,
        private readonly array $payload,
        private readonly array $metadata = [],
        private readonly ?string $idempotencyKey = null,
    ) {}

    public static function make(string $type, array $payload, array $metadata = [], ?string $idempotencyKey = null): self
    {
        return new self($type, $payload, $metadata, $idempotencyKey);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }
}
