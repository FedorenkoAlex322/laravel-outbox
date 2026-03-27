<?php

namespace Outbox\Tests\Unit\DTOs;

use Outbox\Contracts\OutboxEventData;
use Outbox\DTOs\OutboxEventDTO;
use PHPUnit\Framework\TestCase;

class OutboxEventDTOTest extends TestCase
{
    public function test_it_can_be_created_via_constructor(): void
    {
        $dto = new OutboxEventDTO(
            type: 'order.created',
            payload: ['order_id' => 1],
            metadata: ['source' => 'api'],
            idempotencyKey: 'key-123',
        );

        $this->assertInstanceOf(OutboxEventData::class, $dto);
    }

    public function test_it_can_be_created_via_make_factory(): void
    {
        $dto = OutboxEventDTO::make(
            type: 'order.created',
            payload: ['order_id' => 1],
            metadata: ['source' => 'api'],
            idempotencyKey: 'key-123',
        );

        $this->assertInstanceOf(OutboxEventDTO::class, $dto);
    }

    public function test_get_type_returns_correct_value(): void
    {
        $dto = new OutboxEventDTO(type: 'user.registered', payload: ['user_id' => 42]);

        $this->assertSame('user.registered', $dto->getType());
    }

    public function test_get_payload_returns_correct_value(): void
    {
        $payload = ['order_id' => 1, 'amount' => 99.99];
        $dto = new OutboxEventDTO(type: 'order.created', payload: $payload);

        $this->assertSame($payload, $dto->getPayload());
    }

    public function test_get_metadata_returns_correct_value(): void
    {
        $metadata = ['source' => 'api', 'ip' => '127.0.0.1'];
        $dto = new OutboxEventDTO(type: 'order.created', payload: [], metadata: $metadata);

        $this->assertSame($metadata, $dto->getMetadata());
    }

    public function test_get_idempotency_key_returns_correct_value(): void
    {
        $dto = new OutboxEventDTO(
            type: 'order.created',
            payload: [],
            idempotencyKey: 'unique-key-456',
        );

        $this->assertSame('unique-key-456', $dto->getIdempotencyKey());
    }

    public function test_default_metadata_is_empty_array(): void
    {
        $dto = new OutboxEventDTO(type: 'order.created', payload: ['id' => 1]);

        $this->assertSame([], $dto->getMetadata());
    }

    public function test_default_idempotency_key_is_null(): void
    {
        $dto = new OutboxEventDTO(type: 'order.created', payload: ['id' => 1]);

        $this->assertNull($dto->getIdempotencyKey());
    }

    public function test_make_factory_default_metadata_is_empty_array(): void
    {
        $dto = OutboxEventDTO::make(type: 'order.created', payload: ['id' => 1]);

        $this->assertSame([], $dto->getMetadata());
    }

    public function test_make_factory_default_idempotency_key_is_null(): void
    {
        $dto = OutboxEventDTO::make(type: 'order.created', payload: ['id' => 1]);

        $this->assertNull($dto->getIdempotencyKey());
    }
}
