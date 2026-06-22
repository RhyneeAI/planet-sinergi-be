<?php

use App\Models\Company;
use App\Models\OpsIncome;
use App\Models\OpsExpense;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->owner = User::factory()->owner()->create(['company_id' => $this->company->id]);

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
});

it('admin can view admin dashboard', function () {
    OpsIncome::factory()->create([
        'amount' => 1000000,
        'date' => now()->toDateString(),
        'company_id' => $this->company->id,
    ]);

    OpsExpense::factory()->create([
        'amount' => 500000,
        'date' => now()->toDateString(),
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/dashboard/admin')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['total_income', 'total_expense', 'sub_companies'],
        ]);
});

it('owner can view admin dashboard', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/operational/dashboard/admin')
        ->assertOk();
});

it('mandor can view mandor dashboard', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/dashboard/mandor')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['total_income', 'total_expense']]);
});

it('mandor dashboard shows their own data only', function () {
    OpsIncome::factory()->create([
        'amount' => 500000,
        'date' => now()->toDateString(),
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'company_id' => $this->company->id,
    ]);

    $otherMandor = User::factory()->mandor()->create(['company_id' => $this->company->id]);
    OpsIncome::factory()->create([
        'amount' => 999999,
        'date' => now()->toDateString(),
        'mandor_id' => $otherMandor->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/dashboard/mandor')
        ->assertOk()
        ->assertJsonPath('data.total_income', 500000);
});

it('admin dashboard includes sub company breakdown', function () {
    OpsIncome::factory()->create([
        'amount' => 750000,
        'date' => now()->toDateString(),
        'sub_company_id' => $this->subCompany->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/dashboard/admin')
        ->assertOk()
        ->assertJsonPath('data.sub_companies.0.uuid', $this->subCompany->uuid)
        ->assertJsonPath('data.sub_companies.0.total_income', 750000);
});

it('dashboard filters by date range', function () {
    OpsIncome::factory()->create([
        'amount' => 500000,
        'date' => now()->toDateString(),
        'company_id' => $this->company->id,
    ]);
    OpsIncome::factory()->create([
        'amount' => 300000,
        'date' => now()->subMonth()->toDateString(),
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/dashboard/admin?' . http_build_query([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('data.total_income', 500000);
});

it('karyawan cannot access operational dashboard', function () {
    $karyawan = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($karyawan)
        ->getJson('/api/v1/operational/dashboard/admin')
        ->assertForbidden();
});
