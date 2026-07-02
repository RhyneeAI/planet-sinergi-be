<?php

use App\Models\AbsEmployeeProfile;
use App\Models\Company;
use App\Models\Position;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->mandor = User::factory()->mandor()->create(['company_id' => $this->company->id]);
});

it('admin can list positions', function () {
    Position::factory()->count(3)->create(['company_id' => $this->company->id]);
    $this->actingAs($this->admin)
        ->getJson('/api/v1/positions')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('owner can list positions', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/positions')
        ->assertOk();
});

it('mandor cannot list positions', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/positions')
        ->assertForbidden();
});

it('admin can show position detail', function () {
    $position = Position::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->admin)
        ->getJson('/api/v1/positions/' . $position->uuid)
        ->assertOk()
        ->assertJsonPath('data.uuid', $position->uuid);
});

it('admin can create position', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/positions', [
            'name' => 'Staff Gudang',
            'daily_rate' => 150000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Staff Gudang')
        ->assertJsonPath('data.daily_rate', 150000);
});

it('validates required fields on store position', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/positions', [])
        ->assertStatus(422);
});

it('admin can update position', function () {
    $position = Position::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->admin)
        ->patchJson('/api/v1/positions/' . $position->uuid, [
            'name' => 'Staff Updated',
            'daily_rate' => 200000,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Staff Updated')
        ->assertJsonPath('data.daily_rate', 200000);
});

it('admin can delete position', function () {
    $position = Position::factory()->create(['company_id' => $this->company->id]);
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/positions/' . $position->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);
    expect(Position::find($position->id))->toBeNull();
});

it('prevents deleting position with active employees', function () {
    $position = Position::factory()->create(['company_id' => $this->company->id]);
    $employee = User::factory()->karyawan()->create(['company_id' => $this->company->id]);
    AbsEmployeeProfile::where('user_id', $employee->id)->update([
        'position_id' => $position->id,
    ]);
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/positions/' . $position->uuid)
        ->assertStatus(422);
});

it('owner cannot create position', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/positions', [
            'name' => 'Test',
            'daily_rate' => 100000,
        ])
        ->assertForbidden();
});

it('position scoped by company', function () {
    $otherCompany = Company::factory()->create();
    Position::factory()->create(['company_id' => $this->company->id, 'name' => 'Pusat']);
    Position::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Cabang']);
    $this->actingAs($this->admin)
        ->getJson('/api/v1/positions')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
