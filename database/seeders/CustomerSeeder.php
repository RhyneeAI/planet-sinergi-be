<?php

namespace Database\Seeders;

use App\Models\Customer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $customers = [];

        for ($i = 0; $i < 50; $i++) {
            $customers[] = [
                'name' => $faker->name,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'company_id' => $faker->numberBetween(1, 3),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Customer::insert($customers);
    }
}
