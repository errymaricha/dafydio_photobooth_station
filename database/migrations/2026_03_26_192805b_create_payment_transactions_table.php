<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignUuid('print_order_id')->nullable()->constrained('print_orders')->nullOnDelete();
            $table->string('payment_ref', 100)->unique();
            $table->string('payment_method', 30);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('IDR');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('paid_at')->nullable();
            $table->jsonb('raw_response_json')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};