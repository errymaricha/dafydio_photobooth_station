<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('storage_disk', 30)->default('local');
            $table->text('file_path');
            $table->string('file_name', 255);
            $table->string('file_ext', 20)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum_sha256', 128)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('file_category', 30);
            $table->string('created_by_type', 20)->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->timestampsTz();

            $table->index('checksum_sha256');
            $table->index('file_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_files');
    }
};