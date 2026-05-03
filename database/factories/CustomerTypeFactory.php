<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'       => fake()->unique()->word(),
            'discount'   => fake()->randomFloat(2, 0, 50),
            'created_by' => User::factory(),
            'company_id' => Company::factory(),
        ];
    }
}