<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            StationSeeder::class,
            DeviceSeeder::class, // optional
            PrinterSeeder::class,
            TemplateSeeder::class,
            PrintAgentSeeder::class,
            DemoWorkflowSeeder::class,
            VoucherLibrarySeeder::class,
            SessionVoucherSeeder::class,
        ]);
    }
}
