<?php

use App\Models\Company;
use App\Models\OpsEditLog;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->mandor = User::factory()->mandor()->create(['company_id' => $this->company->id]);
});

it('admin can list edit logs', function () {
    OpsEditLog::factory()->count(3)->create([
        'edited_by' => $this->admin->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/edit-logs')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('owner can list edit logs', function () {
    OpsEditLog::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/operational/edit-logs')
        ->assertOk();
});

it('mandor cannot list edit logs', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/edit-logs')
        ->assertForbidden();
});

it('filters edit logs by date range', function () {
    OpsEditLog::factory()->create([
        'company_id' => $this->company->id,
        'created_at' => now()->subDays(5),
    ]);
    OpsEditLog::factory()->create([
        'company_id' => $this->company->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/edit-logs?' . http_build_query([
            'date_from' => now()->subDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters edit logs by loggable type', function () {
    OpsEditLog::factory()->create([
        'loggable_type' => 'App\Models\OpsIncome',
        'company_id' => $this->company->id,
    ]);
    OpsEditLog::factory()->create([
        'loggable_type' => 'App\Models\OpsExpense',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/edit-logs?loggable_type=App\Models\OpsIncome')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('karyawan cannot access edit logs', function () {
    $karyawan = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($karyawan)
        ->getJson('/api/v1/operational/edit-logs')
        ->assertForbidden();
});
