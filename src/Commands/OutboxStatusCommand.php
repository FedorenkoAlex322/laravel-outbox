<?php

namespace Outbox\Commands;

use Illuminate\Console\Command;
use Outbox\Contracts\OutboxManager;

class OutboxStatusCommand extends Command
{
    protected $signature = 'outbox:status';

    protected $description = 'Show outbox event statistics';

    public function handle(OutboxManager $manager): int
    {
        $stats = $manager->getStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pending', $stats['pending']],
                ['Processing', $stats['processing']],
                ['Processed', $stats['processed']],
                ['Failed', $stats['failed']],
                ['Oldest Pending (min)', $stats['oldest_pending_minutes'] ?? 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}
