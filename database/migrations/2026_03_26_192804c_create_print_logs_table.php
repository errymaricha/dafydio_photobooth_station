<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->foreignUuid('print_queue_job_id')->nullable()->constrained('print_queue_jobs')->nullOnDelete();
            $table->foreignUuid('printer_id')->constrained('printers')->restrictOnDelete();
            $table->string('log_level', 20)->default('info');
            $table->text('message');
            $table->jsonb('payload_json')->nullable();
            $table->timestampsTz();

            $table->index(['print_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
    }
};