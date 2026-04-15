<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('package_code', 50)->unique();
            $table->string('package_name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('session_quota')->default(0);
            $table->unsignedInteger('print_quota')->default(0);
            $table->decimal('price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};