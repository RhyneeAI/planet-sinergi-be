<?php

namespace Database\Factories\Ops;

use App\Models\Company;
use App\Models\OpsIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpsEditLogFactory extends Factory
{
    protected $model = \App\Models\OpsEditLog::class;
    public function definition(): array
    {
        $loggable = OpsIncome::factory()->create();

        return [
            'loggable_type' => $loggable->getMorphClass(),
            'loggable_id' => $loggable->id,
            'reason' => fake()->sentence(),
            'old_data' => ['name' => fake()->sentence(2)],
            'new_data' => ['name' => fake()->sentence(2)],
            'edited_by' => User::factory()->admin(),
            'company_id' => Company::factory(),
            'created_at' => now(),
        ];
    }
}
