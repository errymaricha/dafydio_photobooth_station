<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Printer;
use App\Models\Role;
use App\Models\User;

class PrintAgentSeeder extends Seeder
{
    public function run(): void
    {
        $printer = Printer::query()
            ->where('printer_code', 'PRINTER-01')
            ->first();

        $role = Role::where('code', 'print-agent')->first();

        if (! $printer || ! $role) {
            return;
        }

        $user = User::firstOrNew([
            'email' => 'agent@photobooth.local',
        ]);

        if (! $user->exists) {
            $user->id = (string) Str::uuid();
        }

        $user->fill([
            'full_name' => 'Print Agent',
            'password' => Hash::make('password'),
            'status' => 'active',
            'printer_id' => $printer->id,
        ]);
        $user->save();

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
