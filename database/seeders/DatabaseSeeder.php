<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's reference data.
     *
     * No demo accounts are created here: the starter kit's test user (with a
     * known password) is not seeded anymore. Tenants and users are created
     * through the registration flow or the platform:root-admin command.
     */
    public function run(): void
    {
        $this->call(SegmentSeeder::class);
    }
}
