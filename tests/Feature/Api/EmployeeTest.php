<?php

use App\Models\AbsEmployeeProfile;
use App\Models\AbsShift;
use App\Models\Position;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    User::$skipSubCompanyAutoCreate = true;

    $this->mandor = User::factory()->mandor()->create(['company_id' => $this->company->id]);
    $this->subCompany = SubCompany::create([
        'name' => 'Cabang Test',
        'code' => 'TST-01',
        'address' => 'Jl. Test',
        'is_active' => true,
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->jabatan = Position::factory()->create(['company_id' => $this->company->id]);
    $this->shift = AbsShift::factory()->create(['company_id' => $this->company->id]);
});

it('admin can list employees', function () {
    User::factory()->karyawan()->count(3)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('owner can list employees', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/employees')
        ->assertOk();
});

it('mandor cannot list employees', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/employees')
        ->assertForbidden();
});

it('admin can create employee', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password' => 'password123',
            'role' => 'KARYAWAN',
            'position_uuid' => $this->jabatan->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'shift_uuid' => $this->shift->uuid,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Budi Santoso')
        ->assertJsonPath('data.profile.jabatan.uuid', $this->jabatan->uuid);
});

it('admin can show employee detail', function () {
    $employee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/employees/' . $employee->uuid)
        ->assertOk()
        ->assertJsonPath('data.uuid', $employee->uuid);
});

it('admin can update employee', function () {
    $employee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/employees/' . $employee->uuid, [
            'name' => 'Budi Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Budi Updated');
});

it('admin can deactivate employee', function () {
    $employee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/employees/' . $employee->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($employee->fresh()->is_active)->toBeFalse();
});

it('admin can reset employee password', function () {
    $employee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/employees/' . $employee->uuid . '/reset-password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('validates required fields on store employee', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees', [])
        ->assertStatus(422);
});

it('owner cannot create employee', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/employees', [
            'name' => 'Test',
            'phone' => '081111111111',
            'password' => 'password123',
            'role' => 'KARYAWAN',
        ])
        ->assertForbidden();
});

it('mandor cannot create employee', function () {
    $this->actingAs($this->mandor)
        ->postJson('/api/v1/employees', [
            'name' => 'Test',
            'phone' => '081111111111',
            'password' => 'password123',
            'role' => 'KARYAWAN',
        ])
        ->assertForbidden();
});

it('employee list scoped by company', function () {
    $otherCompany = Company::factory()->create();
    $sameKaryawan = User::factory()->karyawan()->create(['company_id' => $this->company->id]);
    $otherKaryawan = User::factory()->karyawan()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonFragment(['uuid' => $sameKaryawan->uuid])
        ->assertJsonMissing(['uuid' => $otherKaryawan->uuid]);
});

it('validates unique phone on create employee', function () {
    User::factory()->create([
        'phone' => '081234567890',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees', [
            'name' => 'Duplikat',
            'phone' => '081234567890',
            'password' => 'password123',
            'role' => 'KARYAWAN',
        ])
        ->assertStatus(422);
});
