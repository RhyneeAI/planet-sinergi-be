<?php

use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);

    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    SubCompany::factory()->create([
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
});

it('admin can list mandors', function () {
    User::factory()->mandor()->count(2)->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('owner can list mandors', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk();
});

it('mandor can list mandors', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk();
});

it('mandor list includes only active mandors', function () {
    User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk();
});

it('mandor list scoped by company', function () {
    $otherCompany = Company::factory()->create();
    User::factory()->mandor()->create([
        'company_id' => $otherCompany->id,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('mandor list with dashboard data includes income and expense sums', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/mandors?is_dashboard_data=true')
        ->assertOk()
        ->assertJsonPath('success', true);
});
