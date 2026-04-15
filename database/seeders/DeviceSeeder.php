<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\AndroidDevice;
use App\Models\Station;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::query()
            ->where('station_code', 'STATION-01')
            ->first();

        if (! $station) {
            return;
        }

        $device = AndroidDevice::firstOrNew([
            'device_code' => 'PB-DEVICE-01',
        ]);

        if (! $device->exists) {
            $device->id = (string) Str::uuid();
        }

        $device->fill([
            'station_id' => $station->id,
            'device_name' => 'Tablet Booth 1',
            'api_key_hash' => Hash::make('secret-device-key'),
            'status' => 'active',
        ]);
        $device->save();
    }
}
