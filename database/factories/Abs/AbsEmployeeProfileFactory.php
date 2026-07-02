<?php

namespace Database\Factories\Abs;

use App\Models\User;
use App\Models\Company;
use App\Models\Position;
use App\Models\SubCompany;
use App\Models\AbsShift;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsEmployeeProfileFactory extends Factory
{
    protected $model = \App\Models\AbsEmployeeProfile::class;
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->karyawan(),
            'position_id' => Position::factory(),
            'sub_company_id' => SubCompany::factory(),
            'abs_shift_id' => AbsShift::factory(),
            'company_id' => fn (array $attrs) => User::find($attrs['user_id'])?->company_id ?? Company::factory(),
        ];
    }
}
