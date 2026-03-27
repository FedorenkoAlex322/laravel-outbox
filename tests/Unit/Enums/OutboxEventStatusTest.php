<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Unit\Enums;

use FedorenkoAlex322\LaravelOutbox\Enums\OutboxEventStatus;
use PHPUnit\Framework\TestCase;

class OutboxEventStatusTest extends TestCase
{
    public function test_it_has_exactly_four_cases(): void
    {
        $cases = OutboxEventStatus::cases();

        $this->assertCount(4, $cases);
    }

    public function test_it_has_pending_case(): void
    {
        $this->assertInstanceOf(OutboxEventStatus::class, OutboxEventStatus::Pending);
    }

    public function test_it_has_processing_case(): void
    {
        $this->assertInstanceOf(OutboxEventStatus::class, OutboxEventStatus::Processing);
    }

    public function test_it_has_processed_case(): void
    {
        $this->assertInstanceOf(OutboxEventStatus::class, OutboxEventStatus::Processed);
    }

    public function test_it_has_failed_case(): void
    {
        $this->assertInstanceOf(OutboxEventStatus::class, OutboxEventStatus::Failed);
    }

    public function test_pending_has_correct_backed_value(): void
    {
        $this->assertSame('pending', OutboxEventStatus::Pending->value);
    }

    public function test_processing_has_correct_backed_value(): void
    {
        $this->assertSame('processing', OutboxEventStatus::Processing->value);
    }

    public function test_processed_has_correct_backed_value(): void
    {
        $this->assertSame('processed', OutboxEventStatus::Processed->value);
    }

    public function test_failed_has_correct_backed_value(): void
    {
        $this->assertSame('failed', OutboxEventStatus::Failed->value);
    }

    public function test_it_can_be_created_from_backed_value(): void
    {
        $this->assertSame(OutboxEventStatus::Pending, OutboxEventStatus::from('pending'));
        $this->assertSame(OutboxEventStatus::Processing, OutboxEventStatus::from('processing'));
        $this->assertSame(OutboxEventStatus::Processed, OutboxEventStatus::from('processed'));
        $this->assertSame(OutboxEventStatus::Failed, OutboxEventStatus::from('failed'));
    }
}
