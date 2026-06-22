<?php

namespace Database\Factories\Pos;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosCustomerTypeFactory extends Factory
{
    protected $model = \App\Models\PosCustomerType::class;

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
