<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::firstOrNew([
            'station_code' => 'STATION-01',
        ]);

        if (! $station->exists) {
            $station->id = (string) Str::uuid();
        }

        $station->fill([
            'station_name' => 'Main Booth',
            'location_name' => 'Default Location',
            'local_ip' => '192.168.1.10',
            'photobooth_price' => 35000,
            'additional_print_price' => 5000,
            'currency_code' => 'IDR',
            'status' => 'online',
        ]);
        $station->save();
    }
}
