<?php

namespace Tests\Feature;

use App\Models\DetectedPrinter;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrinterDiscoveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_agent_can_sync_detected_printers_from_os_scan(): void
    {
        $station = $this->createStation();
        $printAgent = $this->createUserWithRole('print-agent', 'Print Agent');

        Sanctum::actingAs($printAgent);

        $this->postJson('/api/print-agent/printers/sync', [
            'station_id' => $station->id,
            'printers' => [
                [
                    'os_identifier' => 'windows://HP_LaserJet_01',
                    'printer_name' => 'HP LaserJet 01',
                    'printer_type' => 'photo',
                    'connection_type' => 'network',
                    'ip_address' => '192.168.1.30',
                    'port' => 9100,
                    'driver_name' => 'generic-escpos',
                    'paper_size_default' => '4R',
                    'status' => 'ready',
                ],
                [
                    'os_identifier' => 'windows://Canon_CP1300',
                    'printer_name' => 'Canon Selphy CP1300',
                    'printer_type' => 'photo',
                    'connection_type' => 'usb',
                    'status' => 'ready',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Detected printers synced.')
            ->assertJsonPath('station_id', $station->id)
            ->assertJsonPath('synced_count', 2);

        $this->assertDatabaseHas('detected_printers', [
            'station_id' => $station->id,
            'os_identifier' => 'windows://HP_LaserJet_01',
            'printer_name' => 'HP LaserJet 01',
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('detected_printers', [
            'station_id' => $station->id,
            'os_identifier' => 'windows://Canon_CP1300',
            'printer_name' => 'Canon Selphy CP1300',
            'status' => 'ready',
        ]);
    }

    public function test_editor_can_create_printer_from_detection(): void
    {
        $station = $this->createStation();
        $editor = $this->createUserWithRole('admin', 'Administrator');

        $detection = DetectedPrinter::create([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'os_identifier' => 'windows://DNP_DS620',
            'printer_name' => 'DNP DS620',
            'printer_type' => 'photo',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.40',
            'port' => 9100,
            'driver_name' => 'dnp-driver',
            'paper_size_default' => '4R',
            'status' => 'ready',
            'is_default' => true,
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($editor);

        $this->postJson("/api/editor/printer-discovery/{$detection->id}", [
            'printer_name' => 'DNP DS620 Main',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Printer created from detection.')
            ->assertJsonPath('printer.printer_name', 'DNP DS620 Main')
            ->assertJsonPath('printer.station.id', $station->id);

        $detection->refresh();

        self::assertNotNull($detection->linked_printer_id);

        $this->assertDatabaseHas('printers', [
            'id' => $detection->linked_printer_id,
            'station_id' => $station->id,
            'printer_name' => 'DNP DS620 Main',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.40',
            'driver_name' => 'dnp-driver',
            'paper_size_default' => '4R',
            'is_default' => true,
            'status' => 'ready',
        ]);
    }

    private function createStation(): Station
    {
        return Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-'.Str::upper(Str::random(6)),
            'station_name' => 'Station Discovery',
            'location_name' => 'Studio',
            'timezone' => 'Asia/Jakarta',
            'status' => 'online',
        ]);
    }

    private function createUserWithRole(string $code, string $name): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'code' => $code,
            'name' => $name,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
