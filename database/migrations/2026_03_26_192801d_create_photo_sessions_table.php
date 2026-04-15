<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_code', 60)->unique();
            $table->foreignUuid('station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('android_devices')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('session_type', 20)->default('photobooth');
            $table->string('source_type', 20)->default('android');
            $table->unsignedInteger('total_expected_photos')->default(0);
            $table->unsignedInteger('captured_count')->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['station_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_sessions');
    }
};