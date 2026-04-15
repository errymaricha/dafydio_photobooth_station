<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('android_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->string('device_code', 50)->unique();
            $table->string('device_name', 100);
            $table->text('api_key_hash');
            $table->string('local_ip', 64)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('os_version', 30)->nullable();
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->unsignedSmallInteger('battery_percent')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('android_devices');
    }
};