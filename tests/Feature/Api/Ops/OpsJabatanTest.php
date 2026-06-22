<?php

use App\Models\AbsEmployeeProfile;
use App\Models\AbsJabatan;
use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->mandor = User::factory()->mandor()->create(['company_id' => $this->company->id]);
});

it('admin can list jabatans', function () {
    AbsJabatan::factory()->count(3)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/jabatans')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('owner can list jabatans', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/operational/jabatans')
        ->assertOk();
});

it('mandor cannot list jabatans', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/jabatans')
        ->assertForbidden();
});

it('admin can show jabatan detail', function () {
    $jabatan = AbsJabatan::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/jabatans/' . $jabatan->uuid)
        ->assertOk()
        ->assertJsonPath('data.uuid', $jabatan->uuid);
});

it('admin can create jabatan', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/jabatans', [
            'name' => 'Staff Gudang',
            'daily_rate' => 150000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Staff Gudang')
        ->assertJsonPath('data.daily_rate', 150000);
});

it('validates required fields on store jabatan', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/jabatans', [])
        ->assertStatus(422);
});

it('admin can update jabatan', function () {
    $jabatan = AbsJabatan::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/operational/jabatans/' . $jabatan->uuid, [
            'name' => 'Staff Updated',
            'daily_rate' => 200000,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Staff Updated')
        ->assertJsonPath('data.daily_rate', 200000);
});

it('admin can delete jabatan', function () {
    $jabatan = AbsJabatan::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/operational/jabatans/' . $jabatan->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AbsJabatan::find($jabatan->id))->toBeNull();
});

it('prevents deleting jabatan with active employees', function () {
    $jabatan = AbsJabatan::factory()->create(['company_id' => $this->company->id]);
    $employee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);
    AbsEmployeeProfile::where('user_id', $employee->id)->update([
        'abs_jabatan_id' => $jabatan->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/operational/jabatans/' . $jabatan->uuid)
        ->assertStatus(422);
});

it('owner cannot create jabatan', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/operational/jabatans', [
            'name' => 'Test',
            'daily_rate' => 100000,
        ])
        ->assertForbidden();
});

it('jabatan scoped by company', function () {
    $otherCompany = Company::factory()->create();
    AbsJabatan::factory()->create(['company_id' => $this->company->id, 'name' => 'Pusat']);
    AbsJabatan::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Cabang']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/jabatans')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
