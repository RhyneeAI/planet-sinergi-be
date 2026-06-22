<?php

use App\Models\AbsDeduction;
use App\Models\AbsBonus;
use App\Models\AbsEmployeeProfile;
use App\Models\AbsJabatan;
use App\Models\AbsPayrollPeriod;
use App\Models\AbsShift;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();

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

    $this->jabatan = AbsJabatan::factory()->create([
        'company_id' => $this->company->id,
        'daily_rate' => 150000,
    ]);

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

    $this->otherEmployee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);
});

it('employee can view current payroll preview', function () {
    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['ulid', 'gross_salary', 'total_deduction', 'total_bonus', 'net_salary']]);
});

it('employee can view their own payroll detail', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll/' . $period->ulid)
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('employee cannot view another employee payroll detail', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->otherEmployee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll/' . $period->ulid)
        ->assertForbidden();
});

it('employee cannot download slip for non-finalized payroll', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
        'status' => 'draft',
    ]);

    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll/' . $period->ulid . '/slip')
        ->assertStatus(422)
        ->assertJsonPath('message', __('absence.payroll.slip_not_available'));
});

it('employee can download slip for finalized payroll', function () {
    $period = AbsPayrollPeriod::factory()->final()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsDeduction::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsBonus::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->employee)
        ->get('/api/v1/abs/me/payroll/' . $period->ulid . '/slip')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('employee preview shows bonus and deduction info', function () {
    $response = $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll');

    $response->assertOk()
        ->assertJsonStructure(['data' => [
            'total_deduction', 'total_bonus', 'net_salary',
            'deductions', 'bonuses',
        ]]);
});

it('another employee cannot download slip', function () {
    $period = AbsPayrollPeriod::factory()->final()->create([
        'user_id' => $this->otherEmployee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/me/payroll/' . $period->ulid . '/slip')
        ->assertForbidden();
});
