<?php

namespace Database\Factories\Abs;

use App\Models\User;
use App\Models\Company;
use App\Models\AbsJabatan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsPayrollPeriodFactory extends Factory
{
    protected $model = \App\Models\AbsPayrollPeriod::class;
    public function definition(): array
    {
        $now = Carbon::now();
        $dailyRate = fake()->randomFloat(0, 100000, 300000);
        $totalDays = fake()->numberBetween(20, 25);
        $gross = $dailyRate * $totalDays;
        $deduction = fake()->randomFloat(2, 0, 50000);
        $bonus = fake()->randomFloat(2, 0, 100000);

        return [
            'user_id' => User::factory()->karyawan(),
            'period_month' => (int) $now->month,
            'period_year' => (int) $now->year,
            'daily_rate' => $dailyRate,
            'total_days' => $totalDays,
            'gross_salary' => $gross,
            'total_deduction' => $deduction,
            'total_bonus' => $bonus,
            'net_salary' => $gross + $bonus - $deduction,
            'status' => 'draft',
            'generated_at' => $now,
            'company_id' => Company::factory(),
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'final']);
    }
}
