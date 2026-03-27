<?php

namespace FedorenkoAlex322\LaravelOutbox\Tests\Unit\Retry;

use FedorenkoAlex322\LaravelOutbox\Retry\ExponentialBackoffStrategy;
use FedorenkoAlex322\LaravelOutbox\Tests\TestCase;

class ExponentialBackoffStrategyTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('outbox.retry.max_attempts', 5);
        $app['config']->set('outbox.retry.base_delay', 60);
        $app['config']->set('outbox.retry.multiplier', 2.0);
        $app['config']->set('outbox.retry.max_delay', 3600);
    }

    public function test_should_retry_returns_true_when_attempts_less_than_max(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertTrue($strategy->shouldRetry(1));
        $this->assertTrue($strategy->shouldRetry(2));
        $this->assertTrue($strategy->shouldRetry(4));
    }

    public function test_should_retry_returns_false_when_attempts_equal_to_max(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertFalse($strategy->shouldRetry(5));
    }

    public function test_should_retry_returns_false_when_attempts_greater_than_max(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertFalse($strategy->shouldRetry(6));
        $this->assertFalse($strategy->shouldRetry(10));
    }

    public function test_get_delay_grows_exponentially(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        // base_delay=60, multiplier=2.0
        // attempt 1: 60 * 2^0 = 60, with jitter up to 75
        // attempt 2: 60 * 2^1 = 120, with jitter up to 150
        // attempt 3: 60 * 2^2 = 240, with jitter up to 300

        $delay1 = $strategy->getDelay(1);
        $this->assertGreaterThanOrEqual(60, $delay1);
        $this->assertLessThanOrEqual(75, $delay1);

        $delay2 = $strategy->getDelay(2);
        $this->assertGreaterThanOrEqual(120, $delay2);
        $this->assertLessThanOrEqual(150, $delay2);

        $delay3 = $strategy->getDelay(3);
        $this->assertGreaterThanOrEqual(240, $delay3);
        $this->assertLessThanOrEqual(300, $delay3);
    }

    public function test_get_delay_does_not_exceed_max_delay_with_jitter(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        // max_delay=3600, with 25% jitter max is 4500
        $delay = $strategy->getDelay(20);

        $this->assertLessThanOrEqual(4500, $delay);
    }

    public function test_get_max_attempts_returns_config_value(): void
    {
        $strategy = new ExponentialBackoffStrategy();

        $this->assertSame(5, $strategy->getMaxAttempts());
    }
}
