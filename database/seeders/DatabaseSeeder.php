<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds a client database. Run by edunexus:client:create, and safe to rerun.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(InstitutionTypeSeeder::class);
    }
}
