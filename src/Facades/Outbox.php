<?php

namespace FedorenkoAlex322\LaravelOutbox\Facades;

use Illuminate\Support\Facades\Facade;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;

/**
 * @method static \Outbox\Models\OutboxEvent store(\Outbox\Contracts\OutboxEventData $event)
 * @method static int process(int $batchSize = 100)
 * @method static int cleanup()
 * @method static array getStats()
 *
 * @see \Outbox\DefaultOutboxManager
 */
class Outbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OutboxManager::class;
    }
}
