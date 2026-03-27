<?php

namespace Outbox\Commands;

use Illuminate\Console\Command;
use Outbox\Contracts\OutboxManager;

class OutboxCleanupCommand extends Command
{
    protected $signature = 'outbox:cleanup
        {--retain-days= : Override retention days from config}';

    protected $description = 'Clean up old processed outbox events';

    public function handle(OutboxManager $manager): int
    {
        $cleaned = $manager->cleanup();

        $this->info("Cleaned up {$cleaned} event(s).");

        return self::SUCCESS;
    }
}
