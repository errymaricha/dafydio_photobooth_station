<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('station_code', 50)->unique();
            $table->string('station_name', 100);
            $table->string('location_name', 150)->nullable();
            $table->string('local_ip', 64)->nullable();
            $table->text('public_url')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->string('status', 20)->default('offline');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};