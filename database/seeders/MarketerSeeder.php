<?php

namespace Database\Seeders;

use App\Models\Marketer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Str;

class MarketerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        for ($i = 0; $i < 15; $i++) {
            Marketer::create([
                'uuid' => (string) Str::uuid(),
                'name' => $faker->name,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'company_id' => $faker->numberBetween(1, 3),
            ]);
        }
    }
}
