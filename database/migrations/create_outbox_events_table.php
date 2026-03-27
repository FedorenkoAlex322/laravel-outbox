<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the table name from config or use default.
     */
    protected function tableName(): string
    {
        return config('outbox.table', 'outbox_events');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->tableName(), function (Blueprint $table) {
            // Primary key for ordering guarantee
            $table->bigIncrements('id');

            // Public identifier for external systems
            $table->uuid('uuid')->unique();

            // Event type (e.g. "order.created", "user.registered")
            $table->string('type', 255)->index();

            // Event payload and optional metadata
            $table->json('payload');
            $table->json('metadata')->nullable();

            // Idempotency key for deduplication on consumer side
            $table->string('idempotency_key', 255)->nullable()->unique();

            // Processing state
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            // Retry scheduling
            $table->timestamp('next_retry_at')->nullable();

            // Pessimistic locking for concurrent workers
            $table->string('locked_by', 255)->nullable();
            $table->timestamp('locked_until')->nullable();

            // Completion tracking
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Worker polling: SELECT ... WHERE status = 'pending' AND next_retry_at <= now() ORDER BY created_at
            $table->index(['status', 'next_retry_at', 'created_at'], 'idx_outbox_worker_poll');

            // Cleanup: DELETE ... WHERE status = 'processed' AND processed_at < retention_date
            $table->index(['status', 'processed_at'], 'idx_outbox_cleanup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }
};
