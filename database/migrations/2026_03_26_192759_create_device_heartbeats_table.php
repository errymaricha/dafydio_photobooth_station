<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('android_devices')->cascadeOnDelete();
            $table->string('local_ip', 64)->nullable();
            $table->unsignedSmallInteger('battery_percent')->nullable();
            $table->unsignedSmallInteger('network_strength')->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestampTz('heartbeat_at');
            $table->timestampsTz();

            $table->index(['device_id', 'heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};