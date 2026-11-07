<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data (business categories) is seeded everywhere. Demo data
     * is never seeded in production.
     */
    public function run(): void
    {
        $this->call(BusinessCategorySeeder::class);

        if (app()->isProduction()) {
            $this->command?->warn('Production environment: no demo data seeded.');

            return;
        }

        $this->call([DemoDataSeeder::class, CustomerDemoSeeder::class]);
    }
}
