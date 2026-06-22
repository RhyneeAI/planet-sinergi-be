<?php

use App\Models\AbsShift;
use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->karyawan = User::factory()->karyawan()->create(['company_id' => $this->company->id]);
});

it('lists shifts', function () {
    AbsShift::factory()->count(3)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/shifts')
        ->assertOk()
        ->assertJsonPath('success', true);

    $response->assertJsonCount(3, 'data');
});

it('shows single shift', function () {
    $shift = AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/shifts/' . $shift->uuid)
        ->assertOk()
        ->assertJsonPath('data.name', $shift->name);
});

it('owner can list shifts', function () {
    AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/abs/shifts')
        ->assertOk();
});

it('karyawan cannot access shifts', function () {
    $this->actingAs($this->karyawan)
        ->getJson('/api/v1/abs/shifts')
        ->assertForbidden();
});

it('admin can create shift', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/shifts', [
            'name' => 'Shift Malam',
            'start_time' => '20:00',
            'end_time' => '05:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Shift Malam');
});

it('validates required fields on store shift', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/shifts', [])
        ->assertStatus(422);
});

it('admin can update shift', function () {
    $shift = AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/abs/shifts/' . $shift->uuid, [
            'name' => 'Shift Updated',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Shift Updated');
});

it('admin can delete shift', function () {
    $shift = AbsShift::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/abs/shifts/' . $shift->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AbsShift::find($shift->id))->toBeNull();
});

it('owner cannot create shift', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/abs/shifts', [
            'name' => 'Shift Baru',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ])
        ->assertForbidden();
});

it('scopes shift by company', function () {
    $otherCompany = Company::factory()->create();
    AbsShift::factory()->create(['company_id' => $this->company->id, 'name' => 'Shift Pusat']);
    AbsShift::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Shift Cabang']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/shifts')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
