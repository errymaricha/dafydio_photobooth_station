<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 30);
            $table->uuid('entity_id');
            $table->string('direction', 20);
            $table->string('target_system', 20);
            $table->jsonb('payload_json')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(5);
            $table->string('status', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
};