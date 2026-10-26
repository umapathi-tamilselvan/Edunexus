<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds a client database. Run by edunexus:client:create, and safe to rerun.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Development clients also get the
     * sample school; DemoSchoolSeeder refuses to run in production, and
     * tests build their own data.
     */
    public function run(): void
    {
        $this->call([
            InstitutionTypeSeeder::class,
            UserSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSchoolSeeder::class);
        }
    }
}
