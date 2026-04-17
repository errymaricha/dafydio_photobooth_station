<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cloud_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_whatsapp', 30)->unique();
            $table->string('cloud_username', 30)->unique();
            $table->string('cloud_password_hash');
            $table->timestampTz('password_set_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cloud_accounts');
    }
};
