<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_printers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignUuid('linked_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->string('os_identifier', 191);
            $table->string('printer_name', 120);
            $table->string('printer_type', 30)->default('photo');
            $table->string('connection_type', 20)->default('network');
            $table->string('ip_address', 64)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('paper_size_default', 30)->nullable();
            $table->string('status', 20)->default('ready');
            $table->boolean('is_default')->default(false);
            $table->jsonb('capabilities_json')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['station_id', 'os_identifier']);
            $table->index(['station_id', 'linked_printer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_printers');
    }
};
