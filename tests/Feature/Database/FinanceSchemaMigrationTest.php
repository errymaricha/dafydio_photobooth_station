<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_tables_and_key_columns_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('finance_accounts'));
        $this->assertTrue(Schema::hasColumns('finance_accounts', [
            'id',
            'account_code',
            'account_name',
            'account_type',
            'normal_balance',
            'parent_id',
            'is_active',
        ]));

        $this->assertTrue(Schema::hasTable('finance_journal_entries'));
        $this->assertTrue(Schema::hasColumns('finance_journal_entries', [
            'id',
            'entry_no',
            'entry_date',
            'period_month',
            'source_type',
            'source_id',
            'station_id',
            'customer_id',
            'status',
        ]));

        $this->assertTrue(Schema::hasTable('finance_journal_lines'));
        $this->assertTrue(Schema::hasColumns('finance_journal_lines', [
            'id',
            'journal_entry_id',
            'line_no',
            'account_id',
            'debit',
            'credit',
        ]));

        $this->assertTrue(Schema::hasTable('finance_expenses'));
        $this->assertTrue(Schema::hasColumns('finance_expenses', [
            'id',
            'expense_no',
            'station_id',
            'category_code',
            'amount_total',
            'status',
            'incurred_at',
        ]));

        $this->assertTrue(Schema::hasTable('finance_periods'));
        $this->assertTrue(Schema::hasColumns('finance_periods', [
            'id',
            'period_month',
            'start_date',
            'end_date',
            'status',
            'closed_at',
        ]));

        $this->assertTrue(Schema::hasTable('finance_daily_snapshots'));
        $this->assertTrue(Schema::hasColumns('finance_daily_snapshots', [
            'id',
            'snapshot_date',
            'station_id',
            'revenue_amount',
            'expense_amount',
            'gross_profit_amount',
            'net_profit_amount',
        ]));
    }
}
