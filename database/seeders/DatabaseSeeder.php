<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            SupplierSeeder::class,
            UserSeeder::class,
            CustomerTypeSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
