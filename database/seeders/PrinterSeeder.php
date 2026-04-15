<?php

namespace Database\Seeders;

use App\Models\Printer;
use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrinterSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::query()
            ->where('station_code', 'STATION-01')
            ->first();

        if (! $station) {
            return;
        }

        $printer = Printer::firstOrNew([
            'printer_code' => 'PRINTER-01',
        ]);

        if (! $printer->exists) {
            $printer->id = (string) Str::uuid();
        }

        $printer->fill([
            'station_id' => $station->id,
            'printer_name' => 'Default Printer',
            'printer_type' => 'photo',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.20',
            'port' => 9100,
            'driver_name' => 'generic-escpos',
            'paper_size_default' => '4R',
            'is_default' => true,
            'status' => 'ready',
        ]);
        $printer->save();
    }
}
