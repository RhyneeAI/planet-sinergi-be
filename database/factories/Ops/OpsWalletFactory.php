<?php

namespace Database\Factories\Ops;

use App\Models\Company;
use App\Models\User;
use App\Models\SubCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsWalletFactory extends Factory
{
    protected $model = \App\Models\OpsWallet::class;
    public function definition(): array
    {
        return [
            'mandor_id' => User::factory()->mandor(),
            'sub_company_id' => SubCompany::factory(),
            'balance' => fake()->randomFloat(2, 0, 1000000),
            'company_id' => Company::factory(),
        ];
    }
}
