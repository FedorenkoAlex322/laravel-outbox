<?php

namespace Outbox\Contracts;

interface CleanupStrategy
{
    /**
     * Clean up old processed events.
     * Returns the number of events cleaned up.
     */
    public function cleanup(int $retentionDays, int $batchSize): int;
}
