<?php

namespace Database\Factories\Pos;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosSupplierFactory extends Factory
{
    protected $model = \App\Models\PosSupplier::class;

    public function definition(): array
    {
        return [
            'name'       => fake()->company(),
            'address'    => fake()->address(),
            'phone'      => fake()->phoneNumber(),
            'created_by' => User::factory(),
            'company_id' => Company::factory(),
        ];
    }
}
