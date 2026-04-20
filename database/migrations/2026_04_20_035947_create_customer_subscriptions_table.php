<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->foreignUuid('package_id')
                ->constrained('subscription_packages')
                ->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->uuid('upgraded_from_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['customer_id', 'status', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
