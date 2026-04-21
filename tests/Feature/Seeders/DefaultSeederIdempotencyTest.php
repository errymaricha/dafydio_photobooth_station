<?php

namespace Tests\Feature\Seeders;

use App\Models\AndroidDevice;
use App\Models\Printer;
use App\Models\Role;
use App\Models\SessionVoucher;
use App\Models\Station;
use App\Models\SubscriptionPackage;
use App\Models\Template;
use App\Models\TemplateSlot;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeders_can_run_multiple_times_without_duplicate_or_id_drift(): void
    {
        $this->seed(DatabaseSeeder::class);

        $firstAdminId = User::query()->where('email', 'admin@photobooth.local')->value('id');
        $firstAgentId = User::query()->where('email', 'agent@photobooth.local')->value('id');
        $firstStationId = Station::query()->where('station_code', 'STATION-01')->value('id');
        $firstDeviceId = AndroidDevice::query()->where('device_code', 'PB-DEVICE-01')->value('id');
        $firstPrinterId = Printer::query()->where('printer_code', 'PRINTER-01')->value('id');
        $firstTemplateId = Template::query()->where('template_code', 'TPL-2SLOT')->value('id');

        $firstSlotIds = TemplateSlot::query()
            ->where('template_id', $firstTemplateId)
            ->orderBy('slot_index')
            ->pluck('id')
            ->all();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($firstAdminId, User::query()->where('email', 'admin@photobooth.local')->value('id'));
        $this->assertSame($firstAgentId, User::query()->where('email', 'agent@photobooth.local')->value('id'));
        $this->assertSame($firstStationId, Station::query()->where('station_code', 'STATION-01')->value('id'));
        $this->assertSame($firstDeviceId, AndroidDevice::query()->where('device_code', 'PB-DEVICE-01')->value('id'));
        $this->assertSame($firstPrinterId, Printer::query()->where('printer_code', 'PRINTER-01')->value('id'));
        $this->assertSame($firstTemplateId, Template::query()->where('template_code', 'TPL-2SLOT')->value('id'));

        $secondSlotIds = TemplateSlot::query()
            ->where('template_id', $firstTemplateId)
            ->orderBy('slot_index')
            ->pluck('id')
            ->all();

        $this->assertSame($firstSlotIds, $secondSlotIds);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('stations', 1);
        $this->assertDatabaseCount('android_devices', 1);
        $this->assertDatabaseCount('printers', 1);
        $this->assertDatabaseCount('templates', 1);
        $this->assertDatabaseCount('template_slots', 2);
        $this->assertDatabaseCount('subscription_packages', 3);
        $this->assertDatabaseCount('finance_accounts', 7);
        $this->assertDatabaseCount('session_vouchers', 5);
        $this->assertDatabaseCount('vouchers', 5);
        $this->assertDatabaseHas('vouchers', [
            'voucher_code' => 'VCH-SKIP-LOCAL',
            'voucher_type' => 'skip',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('vouchers', [
            'voucher_code' => 'VCH-FREE-EVENT',
            'voucher_type' => 'free',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('vouchers', [
            'voucher_code' => 'VCH-OVERRIDE-QA',
            'voucher_type' => 'override',
            'status' => 'inactive',
        ]);

        $printAgentUser = User::query()->where('email', 'agent@photobooth.local')->firstOrFail();

        $this->assertSame($firstPrinterId, $printAgentUser->printer_id);
        $this->assertTrue(
            $printAgentUser->roles()->where('code', 'print-agent')->exists(),
        );

        $this->assertTrue(
            Role::query()->where('code', 'admin')->exists(),
        );

        $this->assertSame(
            5,
            SessionVoucher::query()->count(),
        );

        $this->assertSame(
            5,
            Voucher::query()->count(),
        );

        $this->assertTrue(
            SubscriptionPackage::query()->where('package_code', 'REGULAR-30')->exists(),
        );
        $this->assertTrue(
            SubscriptionPackage::query()->where('package_code', 'PREMIUM-30')->exists(),
        );
        $this->assertTrue(
            SubscriptionPackage::query()->where('package_code', 'PREMIUM-365')->exists(),
        );
    }
}
