<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'admin', 'name' => 'Administrator'],
            ['code' => 'editor', 'name' => 'Editor'],
            ['code' => 'member', 'name' => 'Member'],
            ['code' => 'print-agent', 'name' => 'Print Agent'],
        ];

        foreach ($roles as $role) {
            $existingId = Role::query()
                ->where('code', $role['code'])
                ->value('id');

            Role::updateOrCreate(
                ['code' => $role['code']],
                [
                    'id' => $existingId ?? (string) Str::uuid(),
                    'name' => $role['name'],
                    'description' => $role['name'],
                ],
            );
        }
    }
}
