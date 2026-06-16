<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\OpsWallet;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create([
        'name' => 'PT Maju Jaya',
        'code' => 'MJ001',
        'address' => 'Jl. Pusat No. 1',
    ]);
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

it('auto creates sub company from company when mandor is created via api', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/admin/mandors', [
            'name' => 'Mandor Baru',
            'phone' => '081234567890',
            'email' => 'mandor@test.com',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('sub_company.name', 'PT Maju Jaya')
        ->assertJsonPath('sub_company.code', 'MJ001-01')
        ->assertJsonPath('sub_company.address', 'Jl. Pusat No. 1');

    $mandor = User::where('phone', '081234567890')->first();

    expect($mandor->role)->toBe(Role::MANDOR);
    expect(SubCompany::where('mandor_id', $mandor->id)->count())->toBe(1);
    expect(OpsWallet::whereHas('subCompany', fn ($q) => $q->where('mandor_id', $mandor->id))->exists())->toBeTrue();
});

it('does not allow manual sub company creation', function () {
    User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'name' => 'Cabang Manual',
            'code' => 'MAN-01',
        ])
        ->assertStatus(405);
});

it('lists only own sub companies for mandor', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $ownSubCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($mandor)
        ->getJson('/api/v1/sub-companies');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.uuid'))->toBe($ownSubCompany->uuid);
});

it('requires sub company uuid for mandor wallet endpoint', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/mandor/wallet')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns wallet for selected sub company', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/mandor/wallet?sub_company_uuid=' . $subCompany->uuid)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sub_company.uuid', $subCompany->uuid);
});
