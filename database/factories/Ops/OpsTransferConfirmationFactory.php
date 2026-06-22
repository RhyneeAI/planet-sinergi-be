<?php

namespace Database\Factories\Ops;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsTransferConfirmationFactory extends Factory
{
    protected $model = \App\Models\OpsTransferConfirmation::class;
    public function definition(): array
    {
        return [
            'confirmable_type' => null,
            'confirmable_id' => null,
            'status' => 'PENDING',
            'confirmed_amount' => 0,
            'mandor_proof_files' => null,
            'confirmed_at' => null,
            'note' => null,
            'confirmed_by' => null,
            'company_id' => Company::factory(),
        ];
    }
}
