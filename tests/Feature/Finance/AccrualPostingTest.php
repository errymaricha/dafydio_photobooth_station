<?php

namespace Tests\Feature\Finance;

use App\Models\PhotoSession;
use App\Models\PrintOrder;
use App\Models\Station;
use Database\Seeders\FinanceAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccrualPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FinanceAccountSeeder::class);
    }

    public function test_photo_session_payment_posts_balanced_journal_entry(): void
    {
        $station = $this->createStation();

        $session = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-001',
            'station_id' => $station->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 2,
        ]);

        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $entry = DB::table('finance_journal_entries')
            ->where('source_type', 'photo_session_payment')
            ->where('source_id', $session->id)
            ->first();

        $this->assertNotNull($entry);

        $lines = DB::table('finance_journal_lines')
            ->join('finance_accounts', 'finance_accounts.id', '=', 'finance_journal_lines.account_id')
            ->where('journal_entry_id', $entry->id)
            ->select('finance_accounts.account_code', 'finance_journal_lines.debit', 'finance_journal_lines.credit')
            ->get()
            ->keyBy('account_code');

        $this->assertCount(3, $lines);
        $this->assertEquals(45000.00, (float) $lines['1100']->debit);
        $this->assertEquals(0.00, (float) $lines['1100']->credit);
        $this->assertEquals(0.00, (float) $lines['4100']->debit);
        $this->assertEquals(35000.00, (float) $lines['4100']->credit);
        $this->assertEquals(0.00, (float) $lines['4110']->debit);
        $this->assertEquals(10000.00, (float) $lines['4110']->credit);
    }

    public function test_paid_print_order_posts_balanced_journal_entry(): void
    {
        $station = $this->createStation();

        $printOrder = PrintOrder::query()->create([
            'id' => (string) Str::uuid(),
            'order_code' => 'PO-FIN-001',
            'station_id' => $station->id,
            'source_type' => 'admin_panel',
            'order_type' => 'session_print',
            'payment_status' => 'paid',
            'total_items' => 1,
            'total_qty' => 1,
            'subtotal_amount' => 9000,
            'discount_amount' => 0,
            'total_amount' => 9000,
            'status' => 'submitted',
            'ordered_at' => now(),
        ]);

        $entry = DB::table('finance_journal_entries')
            ->where('source_type', 'print_order_payment')
            ->where('source_id', $printOrder->id)
            ->first();

        $this->assertNotNull($entry);

        $lines = DB::table('finance_journal_lines')
            ->join('finance_accounts', 'finance_accounts.id', '=', 'finance_journal_lines.account_id')
            ->where('journal_entry_id', $entry->id)
            ->select('finance_accounts.account_code', 'finance_journal_lines.debit', 'finance_journal_lines.credit')
            ->get()
            ->keyBy('account_code');

        $this->assertCount(2, $lines);
        $this->assertEquals(9000.00, (float) $lines['1100']->debit);
        $this->assertEquals(0.00, (float) $lines['1100']->credit);
        $this->assertEquals(0.00, (float) $lines['4120']->debit);
        $this->assertEquals(9000.00, (float) $lines['4120']->credit);
    }

    public function test_photo_session_paid_update_is_idempotent_for_finance_posting(): void
    {
        $station = $this->createStation();

        $session = PhotoSession::query()->create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-FIN-002',
            'station_id' => $station->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'additional_print_count' => 0,
        ]);

        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $session->update([
            'payment_ref' => 'RETRY-REF-001',
        ]);

        $this->assertSame(
            1,
            DB::table('finance_journal_entries')
                ->where('source_type', 'photo_session_payment')
                ->where('source_id', $session->id)
                ->count()
        );
    }

    private function createStation(): Station
    {
        return Station::query()->create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-FIN-'.strtoupper(Str::random(4)),
            'station_name' => 'Finance Station',
            'location_name' => 'HQ',
            'photobooth_price' => 35000,
            'additional_print_price' => 5000,
            'currency_code' => 'IDR',
            'status' => 'online',
        ]);
    }
}
