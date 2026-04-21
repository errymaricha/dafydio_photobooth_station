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
            SubscriptionPackageSeeder::class,
            FinanceAccountSeeder::class,
            PrintAgentSeeder::class,
            DemoWorkflowSeeder::class,
            ExamplePrintOrderSeeder::class,
            VoucherLibrarySeeder::class,
            SessionVoucherSeeder::class,
        ]);
    }
}
