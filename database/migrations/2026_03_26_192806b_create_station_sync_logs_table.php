<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('station_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignUuid('sync_job_id')->constrained('sync_jobs')->cascadeOnDelete();
            $table->string('sync_type', 30)->nullable();
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->timestampsTz();

            $table->index(['station_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_sync_logs');
    }
};