<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * All sample data (no user accounts): the catalogue and a business history on top of it.
 * The installer offers this to new owners who want something to look at.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoInventorySeeder::class, DemoOperationsSeeder::class]);
    }
}
