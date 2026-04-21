<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('period_month', 7);
            $table->string('source_type', 30);
            $table->uuid('source_id')->nullable();
            $table->string('source_ref', 80)->nullable();
            $table->foreignUuid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();
            $table->foreignUuid('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('currency_code', 10)->default('IDR');
            $table->string('status', 20)->default('posted');
            $table->text('memo')->nullable();
            $table->foreignUuid('posted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('reversed_entry_id')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['entry_date', 'status']);
            $table->index(['period_month', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['station_id', 'entry_date']);
            $table->index(['customer_id', 'entry_date']);
        });

        Schema::table('finance_journal_entries', function (Blueprint $table) {
            $table->foreign('reversed_entry_id')
                ->references('id')
                ->on('finance_journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reversed_entry_id']);
        });

        Schema::dropIfExists('finance_journal_entries');
    }
};
