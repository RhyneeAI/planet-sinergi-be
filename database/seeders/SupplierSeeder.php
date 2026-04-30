<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str; 

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $suppliers = [];

        for ($i = 0; $i < 20; $i++) {
            $suppliers[] = [
                'uuid' => (string) Str::uuid(),
                'name' => $faker->company,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'company_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Supplier::insert($suppliers);
    }
}