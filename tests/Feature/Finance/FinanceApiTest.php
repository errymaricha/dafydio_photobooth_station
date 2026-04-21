<?php

namespace Tests\Feature\Finance;

use App\Models\PhotoSession;
use App\Models\PrintOrder;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\FinanceAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_fetch_finance_accounts(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $this->getJson('/api/editor/finance/accounts')
            ->assertOk()
            ->assertJsonPath('accounts.0.account_code', '1100')
            ->assertJsonPath('accounts.6.account_code', '5100');
    }

    public function test_editor_can_fetch_daily_pnl_report_with_station_filter(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $stationA = $this->createStation(
            code: 'ST-FIN-A',
            photoboothPrice: 10000,
            additionalPrintPrice: 2000
        );
        $stationB = $this->createStation(
            code: 'ST-FIN-B',
            photoboothPrice: 5000,
            additionalPrintPrice: 1000
        );

        $sessionA = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-A-1',
            'station_id' => $stationA->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 2,
        ]);
        $sessionA->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $sessionB = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-B-1',
            'station_id' => $stationB->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 0,
        ]);
        $sessionB->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        PrintOrder::query()->create([
            'id' => (string) Str::uuid(),
            'order_code' => 'PO-FIN-A-1',
            'station_id' => $stationA->id,
            'session_id' => $sessionA->id,
            'source_type' => 'admin_panel',
            'order_type' => 'session_print',
            'payment_status' => 'paid',
            'total_items' => 1,
            'total_qty' => 1,
            'subtotal_amount' => 6000,
            'discount_amount' => 0,
            'total_amount' => 6000,
            'status' => 'submitted',
            'ordered_at' => now(),
        ]);

        $this->getJson('/api/editor/finance/reports/daily-pnl?date_from='.now()->toDateString().'&date_to='.now()->toDateString().'&station_id='.$stationA->id)
            ->assertOk()
            ->assertJsonPath('summary.revenue_amount', 20000)
            ->assertJsonPath('summary.expense_amount', 0)
            ->assertJsonPath('summary.net_profit_amount', 20000)
            ->assertJsonPath('by_station.0.station_code', 'ST-FIN-A');
    }

    public function test_daily_pnl_report_validates_maximum_date_range(): void
    {
        Sanctum::actingAs($this->createEditorUser());

        $this->getJson('/api/editor/finance/reports/daily-pnl?date_from=2025-01-01&date_to=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_editor_can_fetch_finance_transactions_with_lines(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $station = $this->createStation(
            code: 'ST-FIN-TX',
            photoboothPrice: 12000,
            additionalPrintPrice: 3000
        );

        $session = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-TX-1',
            'station_id' => $station->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 1,
        ]);
        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->getJson('/api/editor/finance/transactions?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.source_type', 'photo_session_payment')
            ->assertJsonPath('rows.0.total_debit', 15000)
            ->assertJsonPath('rows.0.total_credit', 15000)
            ->assertJsonPath('rows.0.is_balanced', true)
            ->assertJsonCount(3, 'rows.0.lines');
    }

    public function test_finance_transactions_validates_maximum_date_range(): void
    {
        Sanctum::actingAs($this->createEditorUser());

        $this->getJson('/api/editor/finance/transactions?date_from=2025-01-01&date_to=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_editor_can_create_manual_expense_and_post_journal(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $station = $this->createStation(
            code: 'ST-FIN-EX',
            photoboothPrice: 10000,
            additionalPrintPrice: 2000
        );

        $response = $this->postJson('/api/editor/finance/expenses', [
            'station_id' => $station->id,
            'category_code' => 'consumables_paper_ink',
            'vendor_name' => 'Toko Kertas Abadi',
            'description' => 'Pembelian kertas dan tinta',
            'amount_subtotal' => 200000,
            'amount_tax' => 20000,
            'incurred_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_ref' => 'INV-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Expense berhasil disimpan dan dijurnal.');

        $this->assertDatabaseCount('finance_expenses', 1);
        $this->assertDatabaseHas('finance_expenses', [
            'category_code' => 'consumables_paper_ink',
            'amount_total' => 220000,
            'status' => 'paid',
        ]);

        $expenseId = DB::table('finance_expenses')->value('id');
        $entryId = DB::table('finance_journal_entries')
            ->where('source_type', 'manual_expense')
            ->where('source_id', $expenseId)
            ->value('id');

        $this->assertNotNull($entryId);
        $this->assertSame(2, DB::table('finance_journal_lines')->where('journal_entry_id', $entryId)->count());
    }

    public function test_editor_can_fetch_expense_list(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $this->postJson('/api/editor/finance/expenses', [
            'category_code' => 'printer_maintenance',
            'vendor_name' => 'Service Printer',
            'description' => 'Service head printer',
            'amount_subtotal' => 150000,
            'amount_tax' => 0,
            'incurred_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'payment_ref' => 'TRX-123',
        ])->assertCreated();

        $this->getJson('/api/editor/finance/expenses?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('rows.0.category_code', 'printer_maintenance')
            ->assertJsonPath('rows.0.amount_total', 150000)
            ->assertJsonPath('options.categories.printer_maintenance', 'Maintenance Printer');
    }

    public function test_dashboard_api_contains_finance_summary_and_last_seven_days(): void
    {
        $this->seed(FinanceAccountSeeder::class);
        Sanctum::actingAs($this->createEditorUser());

        $station = $this->createStation(
            code: 'ST-FIN-DB',
            photoboothPrice: 12000,
            additionalPrintPrice: 3000
        );

        $session = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-DB-1',
            'station_id' => $station->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 1,
        ]);
        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->getJson('/api/editor/dashboard')
            ->assertOk()
            ->assertJsonPath('finance.today.revenue_amount', 15000)
            ->assertJsonPath('finance.today.expense_amount', 0)
            ->assertJsonPath('finance.today.net_profit_amount', 15000)
            ->assertJsonStructure([
                'finance' => [
                    'today' => [
                        'revenue_amount',
                        'expense_amount',
                        'gross_profit_amount',
                        'net_profit_amount',
                    ],
                    'last_7_days',
                ],
            ]);
    }

    private function createEditorUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Administrator']
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createStation(
        string $code,
        float $photoboothPrice,
        float $additionalPrintPrice
    ): Station {
        return Station::query()->create([
            'id' => (string) Str::uuid(),
            'station_code' => $code,
            'station_name' => 'Finance '.$code,
            'location_name' => 'HQ',
            'photobooth_price' => $photoboothPrice,
            'additional_print_price' => $additionalPrintPrice,
            'currency_code' => 'IDR',
            'status' => 'online',
        ]);
    }
}
