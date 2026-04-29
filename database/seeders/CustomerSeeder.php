<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerType;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $customerTypeIds = CustomerType::pluck('id')->toArray();

        for ($i = 0; $i < 25; $i++) {
            Customer::create([
                'uuid' => (string) Str::uuid(),
                'name' => $faker->name,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'customer_type_id' => $faker->randomElement($customerTypeIds),
                'company_id' => $faker->numberBetween(1, 3),
            ]);
        }
    }
}