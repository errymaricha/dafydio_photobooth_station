<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')
                ->constrained('finance_journal_entries')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignUuid('account_id')
                ->constrained('finance_accounts')
                ->restrictOnDelete();
            $table->foreignUuid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();
            $table->foreignUuid('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->string('currency_code', 10)->default('IDR');
            $table->timestampsTz();

            $table->unique(['journal_entry_id', 'line_no']);
            $table->index(['account_id', 'created_at']);
            $table->index(['station_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });

        DB::statement(
            'alter table finance_journal_lines
            add constraint finance_journal_lines_amount_check
            check (
                debit >= 0 and credit >= 0
                and ((debit > 0 and credit = 0) or (credit > 0 and debit = 0))
            )'
        );
    }

    public function down(): void
    {
        DB::statement('alter table finance_journal_lines drop constraint if exists finance_journal_lines_amount_check');
        Schema::dropIfExists('finance_journal_lines');
    }
};
