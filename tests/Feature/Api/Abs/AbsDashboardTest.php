<?php

use App\Models\AbsAttendance;
use App\Models\AbsEmployeeProfile;
use App\Models\Position;
use App\Models\AbsShift;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->company = Company::factory()->create();

    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    User::$skipSubCompanyAutoCreate = true;
    $this->subCompany = SubCompany::create([
        'name' => 'Cabang Test',
        'code' => 'TST-01',
        'address' => 'Jl. Test',
        'is_active' => true,
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->position = Position::factory()->create(['company_id' => $this->company->id]);
    $this->shift = AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->employee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    AbsEmployeeProfile::where('user_id', $this->employee->id)->update([
        'position_id' => $this->position->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
    ]);
});

it('returns dashboard for admin', function () {
    $admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $today = now()->toDateString();

    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
        'status' => 'hadir',
        'date' => $today,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/abs/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['active_employees', 'present_today', 'late_today', 'attendance_chart']]);
});

it('returns dashboard for owner', function () {
    $owner = User::factory()->owner()->create(['company_id' => $this->company->id]);

    $this->actingAs($owner)
        ->getJson('/api/v1/abs/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('karyawan cannot access dashboard', function () {
    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/dashboard')
        ->assertForbidden();
});

it('dashboard is scoped by company', function () {
    $admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->karyawan()->create(['company_id' => $otherCompany->id]);

    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
        'status' => 'hadir',
        'date' => now()->toDateString(),
    ]);

    AbsAttendance::factory()->create([
        'user_id' => $otherUser->id,
        'company_id' => $otherCompany->id,
        'status' => 'hadir',
        'date' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/abs/dashboard')
        ->assertOk()
        ->assertJsonPath('data.present_today', 1);
});
