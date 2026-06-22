<?php

namespace Database\Factories\Abs;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsJabatanFactory extends Factory
{
    protected $model = \App\Models\AbsJabatan::class;
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'daily_rate' => fake()->randomFloat(0, 100000, 500000),
            'company_id' => Company::factory(),
        ];
    }
}
