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

it('creates mandor with new branch via sub company name', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/mandors', [
            'name' => 'Mandor Baru',
            'phone' => '081234567890',
            'email' => 'mandor@test.com',
            'sub_company_name' => 'Cabang Jakarta',
            'sub_company_code' => 'JKT-01',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('sub_company.name', 'Cabang Jakarta')
        ->assertJsonPath('sub_company.code', 'JKT-01')
        ->assertJsonPath('data.has_sub_company', true)
        ->assertJsonPath('data.sub_company.name', 'Cabang Jakarta');

    $mandor = User::where('phone', '081234567890')->first();

    expect($mandor->role)->toBe(Role::MANDOR);
    expect(SubCompany::where('mandor_id', $mandor->id)->count())->toBe(1);
    expect(OpsWallet::whereHas('subCompany', fn ($q) => $q->where('mandor_id', $mandor->id))->exists())->toBeTrue();
});

it('requires branch fields when creating mandor via api', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/mandors', [
            'name' => 'Mandor Baru',
            'phone' => '081234567891',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sub_company_uuid', 'sub_company_name']);
});

it('rejects both branch options when creating mandor', function () {
    $existing = SubCompany::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => User::factory()->mandor()->create(['company_id' => $this->company->id])->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/mandors', [
            'name' => 'Mandor Baru',
            'phone' => '081234567892',
            'sub_company_uuid' => $existing->uuid,
            'sub_company_name' => 'Cabang Baru',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sub_company_uuid', 'sub_company_name']);
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

it('lists operational sub companies for admin', function () {
    User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/sub-companies');

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data'))->not->toBeEmpty();
});

it('infers sub company automatically for single branch mandor wallet endpoint', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/wallet')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sub_company.uuid', $subCompany->uuid);
});

it('requires sub company uuid when mandor has multiple branches', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    SubCompany::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => $mandor->id,
        'name' => 'Cabang Kedua',
        'code' => 'MJ001-99',
    ]);

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/wallet')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sub_company_uuid']);
});

it('returns wallet for selected sub company', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/wallet?sub_company_uuid=' . $subCompany->uuid)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sub_company.uuid', $subCompany->uuid);
});

it('allows mandor to fetch only their own mandor profile', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'name' => 'Mandor Satu',
    ]);

    User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'name' => 'Mandor Lain',
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($mandor)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $mandor->uuid)
        ->assertJsonPath('data.0.has_sub_company', true)
        ->assertJsonPath('data.0.sub_company.uuid', $subCompany->uuid);
});

it('includes sub companies in login response for mandor', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->postJson('/api/v1/login', [
        'phone' => $mandor->phone,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.sub_companies.0.uuid', $subCompany->uuid);
});
