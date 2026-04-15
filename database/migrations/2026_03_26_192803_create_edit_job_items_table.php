<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edit_job_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('edit_job_id')->constrained('edit_jobs')->cascadeOnDelete();
            $table->foreignUuid('session_photo_id')->constrained('session_photos')->restrictOnDelete();
            $table->unsignedInteger('slot_index');
            $table->jsonb('crop_json')->nullable();
            $table->jsonb('transform_json')->nullable();
            $table->jsonb('filter_json')->nullable();
            $table->timestampsTz();

            $table->index(['edit_job_id', 'slot_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_job_items');
    }
};