<?php

namespace Database\Factories\Abs;

use App\Enums\AbsLoanStatus;
use App\Models\AbsLoan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsLoanFactory extends Factory
{
    protected $model = AbsLoan::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 100000, 10000000);
        $tenor = $this->faker->numberBetween(1, 4);
        $installment = round($amount / $tenor, 2);

        return [
            'user_id' => User::factory(),
            'amount' => $amount,
            'reason' => $this->faker->sentence(),
            'tenor_months' => $tenor,
            'monthly_installment' => $installment,
            'remaining_balance' => $amount,
            'status' => AbsLoanStatus::PENDING,
            'approved_by' => null,
            'company_id' => fn(array $attr) => User::find($attr['user_id'])?->company_id ?? 1,
        ];
    }
}
