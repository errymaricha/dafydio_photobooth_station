<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('printer_status_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('printer_id')->constrained('printers')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampsTz();

            $table->index(['printer_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_status_logs');
    }
};