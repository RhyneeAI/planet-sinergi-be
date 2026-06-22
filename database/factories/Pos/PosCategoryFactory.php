<?php

namespace Database\Factories\Pos;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosCategoryFactory extends Factory
{
    protected $model = \App\Models\PosCategory::class;

    public function definition(): array
    {
        return [
            'name'       => fake()->unique()->word(),
            'created_by' => User::factory(),
            'company_id' => Company::factory(),
        ];
    }
}
