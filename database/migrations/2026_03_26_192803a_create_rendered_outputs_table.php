<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rendered_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('photo_sessions')->cascadeOnDelete();
            $table->foreignUuid('edit_job_id')->constrained('edit_jobs')->cascadeOnDelete();
            $table->foreignUuid('file_id')->constrained('asset_files')->restrictOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->string('render_type', 20)->default('final_print');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('dpi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('rendered_at')->nullable();
            $table->timestampsTz();

            $table->index(['session_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendered_outputs');
    }
};