<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('print_queue_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->foreignUuid('printer_id')->constrained('printers')->restrictOnDelete();
            $table->string('queue_name', 50)->default('print');
            $table->integer('priority')->default(0);
            $table->jsonb('job_payload')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->string('status', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'printer_id', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_queue_jobs');
    }
};