<?php

use App\Models\Company;
use App\Models\OpsWallet;
use App\Models\OpsWalletTransaction;
use App\Models\SubCompany;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);

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

    $this->wallet = OpsWallet::factory()->create([
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'balance' => 500000,
        'company_id' => $this->company->id,
    ]);
});

it('mandor can view their wallet', function () {
    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/wallet')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['uuid', 'balance']]);
});

it('mandor can view wallet transactions', function () {
    OpsWalletTransaction::factory()->count(3)->create([
        'wallet_id' => $this->wallet->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/wallet/transactions')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('admin cannot view wallet', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/wallet')
        ->assertForbidden();
});

it('admin cannot view wallet transactions', function () {
    OpsWalletTransaction::factory()->create([
        'wallet_id' => $this->wallet->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/wallet/transactions')
        ->assertForbidden();
});

it('karyawan cannot access wallet', function () {
    $karyawan = User::factory()->karyawan()->create(['company_id' => $this->company->id]);

    $this->actingAs($karyawan)
        ->getJson('/api/v1/operational/wallet')
        ->assertForbidden();
});

it('returns empty wallet data when no wallet exists', function () {
    User::$skipSubCompanyAutoCreate = true;
    $newMandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    SubCompany::factory()->create([
        'mandor_id' => $newMandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->actingAs($newMandor)
        ->getJson('/api/v1/operational/wallet')
        ->assertOk()
        ->assertJsonPath('data.balance', 0);
});

it('filters wallet transactions by date', function () {
    OpsWalletTransaction::factory()->create([
        'wallet_id' => $this->wallet->id,
        'company_id' => $this->company->id,
        'created_at' => now()->subDays(5),
    ]);
    OpsWalletTransaction::factory()->create([
        'wallet_id' => $this->wallet->id,
        'company_id' => $this->company->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/wallet/transactions?date_from=' . now()->subDay()->toDateString())
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
