<?php

use App\Models\AbsAttendance;
use App\Models\AbsBonus;
use App\Models\AbsDeduction;
use App\Models\AbsPayrollPeriod;
use App\Models\AbsShift;
use App\Models\AbsJabatan;
use App\Models\AbsEmployeeProfile;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    User::$skipSubCompanyAutoCreate = true;

    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->subCompany = SubCompany::create([
        'name' => 'Cabang Test',
        'code' => 'TST-01',
        'address' => 'Jl. Test',
        'is_active' => true,
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->jabatan = AbsJabatan::factory()->create(['company_id' => $this->company->id]);
    $this->shift = AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->employee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    AbsEmployeeProfile::where('user_id', $this->employee->id)->update([
        'abs_jabatan_id' => $this->jabatan->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
    ]);

    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
});

it('lists attendance report', function () {
    AbsAttendance::factory()
        ->count(3)
        ->sequence(
            ['date' => now()->subDay()],
            ['date' => now()->subDays(2)],
            ['date' => now()->subDays(3)],
        )
        ->create([
            'user_id' => $this->employee->id,
            'sub_company_id' => $this->subCompany->id,
            'abs_shift_id' => $this->shift->id,
            'company_id' => $this->company->id,
        ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/attendance')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('exports attendance report', function () {
    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/attendance?mode=export')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['filename', 'download_url']]);
});

it('lists payroll report', function () {
    $secondEmployee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);
    AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);
    AbsPayrollPeriod::factory()->create([
        'user_id' => $secondEmployee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/payroll')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('exports payroll report', function () {
    AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/payroll?mode=export')
        ->assertOk()
        ->assertJsonStructure(['data' => ['filename', 'download_url']]);
});

it('lists deductions report', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsDeduction::factory()->count(3)->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/deductions')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('exports deductions report', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsDeduction::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/deductions?mode=export')
        ->assertOk()
        ->assertJsonStructure(['data' => ['filename', 'download_url']]);
});

it('lists bonuses report', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsBonus::factory()->count(3)->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/bonuses')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('exports bonuses report', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsBonus::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/bonuses?mode=export')
        ->assertOk()
        ->assertJsonStructure(['data' => ['filename', 'download_url']]);
});

it('lists employees report', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/employees')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('exports employees report', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/reports/employees?mode=export')
        ->assertOk()
        ->assertJsonStructure(['data' => ['filename', 'download_url']]);
});

it('owner can view reports', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/abs/reports/attendance')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('karyawan cannot view reports', function () {
    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/reports/attendance')
        ->assertForbidden();
});
