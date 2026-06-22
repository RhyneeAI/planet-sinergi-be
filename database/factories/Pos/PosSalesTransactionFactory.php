<?php

namespace Database\Factories\Pos;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class PosSalesTransactionFactory extends Factory
{
    protected $model = \App\Models\PosSalesTransaction::class;

    public function definition(): array
    {
        return [
            'ulid'               => Str::ulid(),
            'transaction_code'   => fake()->unique()->bothify('SO-####'),
            'transaction_date'   => fake()->dateTimeThisYear(),
            'discount'           => 0,
            'total'              => fake()->randomFloat(2, 10000, 1000000),
            'paid'               => 0,
            'payment_type'       => PosPaymentType::CASH,
            'transaction_status' => PosTransactionStatus::UNPAID,
            'customer_id'        => PosCustomer::factory(),
            'created_by'         => User::factory(),
            'company_id'         => Company::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['transaction_status' => PosTransactionStatus::PAID->value]);
    }

    public function unpaid(): static
    {
        return $this->state(['transaction_status' => PosTransactionStatus::UNPAID->value]);
    }

    public function pending(): static
    {
        return $this->state(['transaction_status' => PosTransactionStatus::PENDING->value]);
    }

    public function process(): static
    {
        return $this->state(['transaction_status' => PosTransactionStatus::PROCESS->value]);
    }

    public function cancel(): static
    {
        return $this->state(['transaction_status' => PosTransactionStatus::CANCEL->value]);
    }

    public function cash(): static
    {
        return $this->state(['payment_type' => PosPaymentType::CASH->value]);
    }

    public function transfer(): static
    {
        return $this->state(['payment_type' => PosPaymentType::TRANSFER->value]);
    }

    public function qris(): static
    {
        return $this->state(['payment_type' => PosPaymentType::QRIS->value]);
    }
}
