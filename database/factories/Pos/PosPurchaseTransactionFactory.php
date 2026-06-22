<?php

namespace Database\Factories\Pos;

use App\Enums\PosPaymentType;
use App\Enums\PosTransactionStatus;
use App\Models\Company;
use App\Models\PosSupplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosPurchaseTransactionFactory extends Factory
{
    protected $model = \App\Models\PosPurchaseTransaction::class;

    public function definition(): array
    {
        return [
            'transaction_code'   => 'PO-' . fake()->unique()->numerify('####'),
            'transaction_date'   => fake()->dateTime(),
            'discount'           => fake()->randomFloat(2, 0, 10000),
            'total'              => fake()->randomFloat(2, 10000, 100000),
            'paid'               => fake()->randomFloat(2, 0, 100000),
            'payment_type'       => fake()->randomElement([PosPaymentType::CASH->value, PosPaymentType::TRANSFER->value, PosPaymentType::QRIS->value]),
            'transaction_status' => fake()->randomElement(PosTransactionStatus::cases())->value,
            'supplier_id'        => PosSupplier::factory(),
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
