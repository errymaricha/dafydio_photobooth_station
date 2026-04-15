<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->string('printer_code', 50)->unique();
            $table->string('printer_name', 100);
            $table->string('printer_type', 30);
            $table->string('connection_type', 20);
            $table->string('ip_address', 64)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('paper_size_default', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('ready');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->index(['station_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};


