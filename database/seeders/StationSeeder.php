<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Station;

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
            'status' => 'online',
        ]);
        $station->save();
    }
}
