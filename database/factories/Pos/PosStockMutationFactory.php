<?php

namespace Database\Factories\Pos;

use App\Enums\PosStockMutationType;
use App\Models\Company;
use App\Models\PosProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class PosStockMutationFactory extends Factory
{
    protected $model = \App\Models\PosStockMutation::class;

    public function definition(): array
    {
        $type = fake()->randomElement(PosStockMutationType::cases());
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
            'notes' => fake()->sentence(),
            'product_id' => PosProduct::factory(),
            'company_id' => Company::factory(),
            'reference_id' => null,
            'created_by' => User::factory(),
            'created_at' => now(),
        ];
    }

    public function purchaseIn(): static
    {
        return $this->state(['type' => PosStockMutationType::PURCHASE_IN]);
    }

    public function salesOut(): static
    {
        return $this->state(['type' => PosStockMutationType::SALES_OUT]);
    }

    public function adjustIn(): static
    {
        return $this->state(['type' => PosStockMutationType::ADJUST_IN]);
    }

    public function adjustOut(): static
    {
        return $this->state(['type' => PosStockMutationType::ADJUST_OUT]);
    }

    public function opname(): static
    {
        return $this->state(['type' => PosStockMutationType::OPNAME]);
    }
}
