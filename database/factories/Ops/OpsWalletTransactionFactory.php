<?php

namespace Database\Factories\Ops;

use App\Models\Company;
use App\Models\OpsIncome;
use App\Models\OpsWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsWalletTransactionFactory extends Factory
{
    protected $model = \App\Models\OpsWalletTransaction::class;
    public function definition(): array
    {
        $reference = OpsIncome::factory()->create();

        return [
            'wallet_id' => OpsWallet::factory(),
            'type' => fake()->randomElement(['CASH', 'TRANSFER']),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'balance_before' => 0,
            'balance_after' => fn (array $attrs) => $attrs['amount'],
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'note' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'company_id' => Company::factory(),
            'created_at' => now(),
        ];
    }
}
