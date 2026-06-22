<?php

namespace Database\Factories\Abs;

use App\Models\Company;
use App\Models\User;
use App\Models\SubCompany;
use App\Models\AbsShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsAttendanceFactory extends Factory
{
    protected $model = \App\Models\AbsAttendance::class;
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'user_id' => User::factory()->karyawan(),
            'sub_company_id' => SubCompany::factory(),
            'abs_shift_id' => AbsShift::factory(),
            'date' => $date,
            'check_in_time' => Carbon::parse($date)->setTime(8, 0, 0),
            'check_in_photo' => 'attendances/selfie.jpg',
            'check_in_lat' => fake()->latitude(-7, -6),
            'check_in_lng' => fake()->longitude(106, 108),
            'check_out_time' => Carbon::parse($date)->setTime(17, 0, 0),
            'check_out_photo' => 'attendances/selfie-out.jpg',
            'check_out_lat' => fake()->latitude(-7, -6),
            'check_out_lng' => fake()->longitude(106, 108),
            'status' => fake()->randomElement(['hadir', 'terlambat']),
            'company_id' => Company::factory(),
        ];
    }
}
