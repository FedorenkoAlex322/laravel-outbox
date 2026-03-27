<?php

namespace FedorenkoAlex322\LaravelOutbox\Commands;

use Illuminate\Console\Command;
use FedorenkoAlex322\LaravelOutbox\Contracts\OutboxManager;

class OutboxWorkerCommand extends Command
{
    protected $signature = 'outbox:work
        {--batch-size= : Number of events to process per batch}
        {--poll-interval= : Seconds between polling cycles}
        {--memory-limit= : Memory limit in MB}
        {--once : Process a single batch and exit}';

    protected $description = 'Process outbox events and deliver them to the transport';

    protected bool $shouldStop = false;

    public function handle(OutboxManager $manager): int
    {
        $batchSize = (int) ($this->option('batch-size') ?? config('outbox.worker.batch_size', 100));
        $pollInterval = (float) ($this->option('poll-interval') ?? config('outbox.worker.poll_interval', 1.0));
        $memoryLimit = (int) ($this->option('memory-limit') ?? config('outbox.worker.memory_limit', 128));
        $sleepWhenEmpty = (int) config('outbox.worker.sleep_when_empty', 5);

        $this->listenForSignals();

        $this->info("Outbox worker started [batch_size={$batchSize}, poll_interval={$pollInterval}s]");

        do {
            $processed = $manager->process($batchSize);

            if ($processed > 0) {
                $this->line("Processed {$processed} event(s)");
            }

            // Check memory limit
            if (memory_get_usage(true) / 1024 / 1024 >= $memoryLimit) {
                $this->warn('Memory limit reached, stopping worker.');
                break;
            }

            if ($this->shouldStop) {
                $this->info('Received stop signal, shutting down gracefully.');
                break;
            }

            if ($this->option('once')) {
                break;
            }

            // Sleep before next poll
            $sleep = $processed > 0 ? $pollInterval : $sleepWhenEmpty;
            usleep((int) ($sleep * 1_000_000));

        } while (true);

        $this->info('Outbox worker stopped.');

        return self::SUCCESS;
    }

    protected function listenForSignals(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
        }
    }
}
