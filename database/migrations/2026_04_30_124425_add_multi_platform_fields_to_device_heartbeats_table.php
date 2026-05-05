<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_heartbeats', function (Blueprint $table) {
            $table->string('device_type', 30)->nullable()->after('device_id');
            $table->string('os_name', 50)->nullable()->after('app_version');
            $table->string('os_version', 30)->nullable()->after('os_name');
            $table->jsonb('capabilities_json')->nullable()->after('os_version');
            $table->jsonb('metrics_json')->nullable()->after('capabilities_json');
            $table->timestampTz('sync_at')->nullable()->after('heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_heartbeats', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'os_name',
                'os_version',
                'capabilities_json',
                'metrics_json',
                'sync_at',
            ]);
        });
    }
};
