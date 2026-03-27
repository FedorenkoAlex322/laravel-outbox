<?php

namespace Outbox\Commands;

use Illuminate\Console\Command;
use Outbox\Contracts\CleanupStrategy;

class OutboxCleanupCommand extends Command
{
    protected $signature = 'outbox:cleanup
        {--retain-days= : Override retention days from config}';

    protected $description = 'Clean up old processed outbox events';

    public function handle(CleanupStrategy $cleanupStrategy): int
    {
        $retentionDays = (int) ($this->option('retain-days') ?? config('outbox.cleanup.retain_days', 7));
        $batchSize = (int) config('outbox.cleanup.batch_size', 1000);

        $cleaned = $cleanupStrategy->cleanup($retentionDays, $batchSize);

        $this->info("Cleaned up {$cleaned} event(s).");

        return self::SUCCESS;
    }
}
