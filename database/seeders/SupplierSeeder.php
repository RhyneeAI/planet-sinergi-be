<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $suppliers = [];

        for ($i = 0; $i < 20; $i++) {
            $suppliers[] = [
                'name' => $faker->company,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'company_id' => $faker->numberBetween(1, 3),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert batch 20 data
        Supplier::insert($suppliers);
    }
}
