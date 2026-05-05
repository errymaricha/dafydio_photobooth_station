<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('android_devices', function (Blueprint $table) {
            $table->string('device_type', 30)->default('android')->after('device_name');
            $table->string('os_name', 50)->nullable()->after('app_version');
            $table->jsonb('capabilities_json')->nullable()->after('battery_percent');
            $table->jsonb('config_json')->nullable()->after('capabilities_json');
            $table->timestampTz('last_sync_at')->nullable()->after('last_heartbeat_at');

            $table->index(['device_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('android_devices', function (Blueprint $table) {
            $table->dropIndex(['device_type', 'status']);
            $table->dropColumn([
                'device_type',
                'os_name',
                'capabilities_json',
                'config_json',
                'last_sync_at',
            ]);
        });
    }
};
