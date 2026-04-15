<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edit_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('photo_sessions')->cascadeOnDelete();
            $table->foreignUuid('editor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->jsonb('edit_state_json')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['session_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_jobs');
    }
};