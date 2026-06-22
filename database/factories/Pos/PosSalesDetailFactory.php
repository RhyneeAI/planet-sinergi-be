<?php

namespace Database\Factories\Pos;

use App\Models\PosProduct;
use App\Models\PosSalesTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class PosSalesDetailFactory extends Factory
{
    protected $model = \App\Models\PosSalesDetail::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $price = fake()->randomFloat(2, 10000, 100000);
        
        return [
            'ulid' => Str::ulid(),
            'sale_id' => PosSalesTransaction::factory(),
            'product_id' => PosProduct::factory(),
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
