<?php

namespace Database\Factories\Abs;

use App\Enums\AbsOvertimeStatus;
use App\Models\AbsOvertime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsOvertimeFactory extends Factory
{
    protected $model = AbsOvertime::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'start_time' => $this->faker->time('H:i'),
            'end_time' => $this->faker->time('H:i'),
            'reason' => $this->faker->sentence(),
            'status' => AbsOvertimeStatus::PENDING,
            'approved_by' => null,
            'company_id' => fn(array $attr) => User::find($attr['user_id'])?->company_id ?? 1,
        ];
    }
}
