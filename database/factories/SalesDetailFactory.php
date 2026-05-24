<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class SalesDetailFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $price = fake()->randomFloat(2, 10000, 100000);
        
        return [
            'ulid' => Str::ulid(),
            'sale_id' => SalesTransaction::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'marketing_price' => $price - 2000,
            'sell_price' => $price,
            'discount' => fake()->randomFloat(2, 0, 10000),
            'subtotal' => $quantity * $price,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}