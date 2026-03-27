<?php

namespace Outbox\DTOs;

use Outbox\Contracts\OutboxEventData;

final readonly class OutboxEventDTO implements OutboxEventData
{
    public function __construct(
        private string $type,
        private array $payload,
        private array $metadata = [],
        private ?string $idempotencyKey = null,
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
