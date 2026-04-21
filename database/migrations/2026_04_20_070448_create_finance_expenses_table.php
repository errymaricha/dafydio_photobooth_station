<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('expense_no', 50)->unique();
            $table->foreignUuid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();
            $table->string('category_code', 40);
            $table->string('category_name', 100);
            $table->string('vendor_name', 120)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount_subtotal', 14, 2)->default(0);
            $table->decimal('amount_tax', 14, 2)->default(0);
            $table->decimal('amount_total', 14, 2)->default(0);
            $table->string('currency_code', 10)->default('IDR');
            $table->date('incurred_at');
            $table->date('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_ref', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('source_type', 30)->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('attachment_path')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'incurred_at']);
            $table->index(['station_id', 'incurred_at']);
            $table->index(['category_code', 'incurred_at']);
            $table->index(['due_at', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_expenses');
    }
};
