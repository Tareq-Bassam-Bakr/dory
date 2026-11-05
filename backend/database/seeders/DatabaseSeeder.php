<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production databases are never seeded with demo data.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Production environment: no demo data seeded.');

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
