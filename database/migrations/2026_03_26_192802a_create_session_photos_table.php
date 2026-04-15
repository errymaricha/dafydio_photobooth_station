<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('photo_sessions')->cascadeOnDelete();
            $table->unsignedInteger('capture_index');
            $table->unsignedInteger('slot_index')->nullable();
            $table->foreignUuid('original_file_id')->constrained('asset_files')->restrictOnDelete();
            $table->foreignUuid('thumbnail_file_id')->nullable()->constrained('asset_files')->nullOnDelete();
            $table->foreignUuid('composed_preview_file_id')->nullable()->constrained('asset_files')->nullOnDelete();
            $table->string('checksum_sha256', 128)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->boolean('is_selected')->default(true);
            $table->timestampTz('uploaded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['session_id', 'capture_index']);
            $table->index(['session_id', 'slot_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_photos');
    }
};