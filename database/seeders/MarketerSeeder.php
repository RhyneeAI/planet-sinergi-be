<?php

namespace Database\Seeders;

use App\Models\Marketer;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class MarketerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $marketers = [];

        for ($i = 0; $i < 15; $i++) {
            $marketers[] = [
                'name' => $faker->name,
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'company_id' => $faker->numberBetween(1, 3),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Marketer::insert($marketers);
    }
}
