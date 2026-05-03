<?php

namespace Database\Factories;

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

class SalesTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ulid'               => Str::ulid(),
            'transaction_code'   => fake()->unique()->bothify('SO-####'),
            'transaction_date'   => fake()->dateTimeThisYear(),
            'discount'           => 0,
            'total'              => fake()->randomFloat(2, 10000, 1000000),
            'paid'               => 0,
            'payment_type'       => PaymentType::CASH,
            'transaction_status' => TransactionStatus::UNPAID,
            'customer_id'        => Customer::factory(),
            'created_by'         => User::factory(),
            'company_id'         => Company::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(['transaction_status' => TransactionStatus::PAID->value]);
    }

    public function unpaid(): static
    {
        return $this->state(['transaction_status' => TransactionStatus::UNPAID->value]);
    }

    public function pending(): static
    {
        return $this->state(['transaction_status' => TransactionStatus::PENDING->value]);
    }

    public function process(): static
    {
        return $this->state(['transaction_status' => TransactionStatus::PROCESS->value]);
    }

    public function cancel(): static
    {
        return $this->state(['transaction_status' => TransactionStatus::CANCEL->value]);
    }

    public function cash(): static
    {
        return $this->state(['payment_type' => PaymentType::CASH->value]);
    }

    public function transfer(): static
    {
        return $this->state(['payment_type' => PaymentType::TRANSFER->value]);
    }

    public function qris(): static
    {
        return $this->state(['payment_type' => PaymentType::QRIS->value]);
    }
}
