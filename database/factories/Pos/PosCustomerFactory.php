<?php

namespace Database\Factories\Pos;

use App\Models\Company;
use App\Models\PosCustomerType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosCustomerFactory extends Factory
{
    protected $model = \App\Models\PosCustomer::class;

    public function definition(): array
    {
        return [
            'name'             => fake()->name(),
            'address'          => fake()->optional()->address(),
            'phone'            => fake()->optional()->numerify('08##########'),
            'customer_type_id' => PosCustomerType::factory(), 
            'created_by'       => User::factory(),
            'company_id'       => Company::factory(),
        ];
    }

    public function withType(?int $customerTypeId = null): static
    {
        return $this->state([
            'customer_type_id' => $customerTypeId ?? PosCustomerType::factory(),
        ]);
    }
}
