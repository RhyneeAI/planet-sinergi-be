<?php

namespace Database\Factories;

use App\Enums\StockMutationType;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class StockMutationFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(StockMutationType::cases());
        $quantity = fake()->numberBetween(1, 100);
        $stockBefore = fake()->numberBetween(0, 500);
        $stockAfter = $type->isIncoming() 
            ? $stockBefore + $quantity 
            : $stockBefore - $quantity;

        return [
            'ulid' => Str::ulid(),
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'notes' => fake()->optional()->sentence(),
            'product_id' => Product::factory(),
            'company_id' => Company::factory(),
            'reference_id' => null,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }

    public function purchaseIn(): static
    {
        return $this->state(['type' => StockMutationType::PURCHASE_IN]);
    }

    public function salesOut(): static
    {
        return $this->state(['type' => StockMutationType::SALES_OUT]);
    }

    public function adjustIn(): static
    {
        return $this->state(['type' => StockMutationType::ADJUST_IN]);
    }

    public function adjustOut(): static
    {
        return $this->state(['type' => StockMutationType::ADJUST_OUT]);
    }

    public function opname(): static
    {
        return $this->state(['type' => StockMutationType::OPNAME]);
    }
}