<?php

use App\Models\AbsAttendance;
use App\Models\AbsEmployeeProfile;
use App\Models\AbsJabatan;
use App\Models\AbsShift;
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

it('admin can list attendances', function () {
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
        ->getJson('/api/v1/abs/attendances')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('admin can show single attendance', function () {
    $attendance = AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/attendances/' . $attendance->ulid)
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('owner can list attendances', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/abs/attendances')
        ->assertOk();
});

it('karyawan cannot list attendances', function () {
    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/attendances')
        ->assertForbidden();
});

it('filters attendance by status', function () {
    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
        'status' => 'hadir',
        'date' => now()->subDays(2),
    ]);

    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
        'status' => 'terlambat',
        'date' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/attendances?status=terlambat')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('attendances scoped by company', function () {
    $otherCompany = Company::factory()->create();
    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
    ]);
    AbsAttendance::factory()->create(['company_id' => $otherCompany->id, 'date' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/attendances')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
