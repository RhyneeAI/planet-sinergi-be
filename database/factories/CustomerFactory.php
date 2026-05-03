<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'             => fake()->name(),
            'address'          => fake()->optional()->address(),
            'phone'            => fake()->optional()->numerify('08##########'),
            'customer_type_id' => CustomerType::factory(), 
            'created_by'       => User::factory(),
            'company_id'       => Company::factory(),
        ];
    }

    public function withType(?int $customerTypeId = null): static
    {
        return $this->state([
            'customer_type_id' => $customerTypeId ?? CustomerType::factory(),
        ]);
    }
}