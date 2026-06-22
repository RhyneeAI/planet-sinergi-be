<?php

namespace Database\Factories\Abs;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsShiftFactory extends Factory
{
    protected $model = \App\Models\AbsShift::class;
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' Shift',
            'start_time' => fake()->randomElement(['08:00:00', '07:00:00', '09:00:00']),
            'end_time' => fake()->randomElement(['17:00:00', '16:00:00', '18:00:00']),
            'company_id' => Company::factory(),
        ];
    }
}
