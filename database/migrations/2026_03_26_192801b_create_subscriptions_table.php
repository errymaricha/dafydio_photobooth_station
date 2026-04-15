<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('package_id')->constrained('subscription_packages')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('session_quota')->default(0);
            $table->unsignedInteger('print_quota')->default(0);
            $table->unsignedInteger('used_sessions')->default(0);
            $table->unsignedInteger('used_prints')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->index(['user_id', 'status', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};