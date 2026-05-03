<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('PRD-####'),
            'base_price' => fake()->randomFloat(2, 1000, 50000),
            'sales_price' => fake()->randomFloat(2, 5000, 100000),
            'last_purchase_price' => fake()->randomFloat(2, 1000, 50000),
            'stock' => fake()->numberBetween(0, 100),
            'min_stock' => fake()->numberBetween(0, 10),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'created_by' => User::factory(),
            'company_id' => Company::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}