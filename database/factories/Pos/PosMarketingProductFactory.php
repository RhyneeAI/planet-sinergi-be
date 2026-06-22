<?php

namespace Database\Factories\Pos;

use App\Models\Company;
use App\Models\PosMarketingProduct;
use App\Models\PosProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosMarketingProductFactory extends Factory
{
    protected $model = PosMarketingProduct::class;

    public function definition(): array
    {
        return [
            'marketing_price' => fake()->randomFloat(2, 5000, 100000),
            'product_id'      => PosProduct::factory(),
            'marketing_id'    => User::factory()->marketing(),
            'created_by'      => User::factory(),
            'company_id'      => Company::factory(),
        ];
    }
}
