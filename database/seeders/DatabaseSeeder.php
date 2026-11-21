<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the demo site: roles, one sign-in per role (password "111111") and the sample data.
 * Never run this on a live installation, the demo passwords are public.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        foreach (['admin', 'manager', 'staff'] as $role) {
            User::factory()->create([
                'name' => ucfirst($role).' Demo',
                'email' => $role === 'admin' ? 'admin@gmail.com' : "{$role}@rentdesk.test",
                'password' => Hash::make('111111'),
            ])->assignRole($role);
        }

        $this->call(DemoDataSeeder::class);
    }
}
