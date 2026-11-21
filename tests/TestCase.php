<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Booting the app applies the stored settings and languages when the tables already exist (MySQL), which
        // would cache them before the test creates its own rows.
        Cache::flush();
    }

    /**
     * Create a user holding the given role (admin, manager or staff).
     */
    protected function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create()->assignRole($role);
    }
}
