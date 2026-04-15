<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew([
            'email' => 'admin@photobooth.local',
        ]);

        if (! $admin->exists) {
            $admin->id = (string) Str::uuid();
        }

        $admin->fill([
            'full_name' => 'Super Admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $admin->save();

        $role = Role::where('code', 'admin')->first();

        if ($role) {
            $admin->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
