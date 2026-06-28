<?php

use App\Enums\OpsExpenseType;
use App\Enums\OpsSourceType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\OpsWallet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('creates sub company with mandor via nested payload', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor Baru',
                'phone' => '081234567890',
                'email' => 'mandor@test.com',
                'address' => 'Jl. Mandor No. 1',
            ],
            'sub_company' => [
                'name' => 'Cabang Jakarta',
                'address' => 'Jl. Cabang Jakarta No. 2',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sub_company.name', 'Cabang Jakarta')
        ->assertJsonPath('data.sub_company.address', 'Jl. Cabang Jakarta No. 2')
        ->assertJsonPath('data.sub_company.code', 'MJ001-01')
        ->assertJsonPath('data.mandor.name', 'Mandor Baru')
        ->assertJsonPath('data.mandor.phone', '081234567890')
        ->assertJsonPath('data.mandor.has_sub_company', true)
        ->assertJsonPath('data.credentials.phone', '081234567890')
        ->assertJsonStructure(['data' => ['credentials' => ['phone', 'password']]]);

    expect($response->json('message'))->toContain('081234567890')
        ->and($response->json('message'))->toContain($response->json('data.credentials.password'))
        ->and($response->json('data.credentials.password'))->toMatch('/^mandorbaru\d{3}$/');

    $mandor = User::where('phone', '081234567890')->first();

    expect($mandor->role)->toBe(Role::MANDOR);
    expect(SubCompany::where('mandor_id', $mandor->id)->count())->toBe(1);
    expect(OpsWallet::whereHas('subCompany', fn ($q) => $q->where('mandor_id', $mandor->id))->exists())->toBeTrue();
});

it('requires mandor and sub company payload when creating branch', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor Baru',
                'phone' => '081234567891',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sub_company', 'sub_company.name']);
});

it('creates sub company from general api route', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor API',
                'phone' => '081234567892',
            ],
            'sub_company' => [
                'name' => 'Cabang Bandung',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.sub_company.name', 'Cabang Bandung');
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
        ->getJson('/api/v1/sub-companies');

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
        ->assertJsonPath('data.user.sub_company_uuid', $subCompany->uuid)
        ->assertJsonPath('data.user.sub_companies.0.uuid', $subCompany->uuid);
});

it('returns null sub company uuid on login when mandor has multiple branches', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    SubCompany::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => $mandor->id,
        'name' => 'Cabang Kedua',
        'code' => 'MJ001-99',
    ]);

    $this->postJson('/api/v1/login', [
        'phone' => $mandor->phone,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.sub_company_uuid', null)
        ->assertJsonCount(2, 'data.user.sub_companies');
});

it('allows admin to update sub company and mandor together', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'name' => 'Mandor Lama',
        'phone' => '081111111111',
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/sub-companies/' . $subCompany->uuid, [
            'mandor' => [
                'name' => 'Mandor Updated',
                'phone' => '082222222222',
                'email' => 'mandor.updated@test.com',
            ],
            'sub_company' => [
                'name' => 'Cabang Updated',
                'address' => 'Alamat Baru',
                'is_active' => false,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.sub_company.name', 'Cabang Updated')
        ->assertJsonPath('data.sub_company.address', 'Alamat Baru')
        ->assertJsonPath('data.sub_company.is_active', false)
        ->assertJsonPath('data.mandor.name', 'Mandor Updated')
        ->assertJsonPath('data.mandor.phone', '082222222222')
        ->assertJsonPath('data.mandor.email', 'mandor.updated@test.com');

    expect($subCompany->fresh())
        ->name->toBe('Cabang Updated')
        ->address->toBe('Alamat Baru')
        ->is_active->toBeFalse();

    expect($mandor->fresh())
        ->name->toBe('Mandor Updated')
        ->phone->toBe('082222222222')
        ->email->toBe('mandor.updated@test.com');
});

it('includes mandor details on sub company show', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'phone' => '083333333333',
        'email' => 'mandor.show@test.com',
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/sub-companies/' . $subCompany->uuid)
        ->assertOk()
        ->assertJsonPath('data.sub_company.uuid', $subCompany->uuid)
        ->assertJsonPath('data.mandor.uuid', $mandor->uuid)
        ->assertJsonPath('data.mandor.phone', '083333333333')
        ->assertJsonPath('data.mandor.email', 'mandor.show@test.com');
});

it('allows admin to delete sub company without pending transfers', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();
    $income = OpsIncome::create([
        'name' => 'Pemasukan Cabang',
        'amount' => 50000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'source_type' => OpsSourceType::INTERNAL,
        'mandor_id' => $mandor->id,
        'sub_company_id' => $subCompany->id,
        'created_by' => $this->admin->id,
        'company_id' => $this->company->id,
    ]);
    $expense = OpsExpense::create([
        'name' => 'Pengeluaran Cabang',
        'amount' => 25000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'expense_type' => OpsExpenseType::INTERNAL,
        'mandor_id' => $mandor->id,
        'sub_company_id' => $subCompany->id,
        'created_by' => $this->admin->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/sub-companies/' . $subCompany->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(SubCompany::withTrashed()->find($subCompany->id)?->trashed())->toBeTrue();
    expect(OpsIncome::withTrashed()->find($income->id)?->trashed())->toBeTrue();
    expect(OpsExpense::withTrashed()->find($expense->id)?->trashed())->toBeTrue();
    expect(User::withTrashed()->find($mandor->id)?->trashed())->toBeTrue();
    expect(OpsWallet::where('sub_company_id', $subCompany->id)->exists())->toBeTrue();
});

it('forbids deleting sub company with pending transfer confirmation', function () {
    Storage::fake('public');

    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);
    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/expenses', [
            'expense_type' => 'MANDOR',
            'mandor_uuid' => $mandor->uuid,
            'sub_company_uuid' => $subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/sub-companies/' . $subCompany->uuid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sub_company_uuid']);
});

it('forbids mandor from updating sub company', function () {
    $mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);
    $subCompany = SubCompany::where('mandor_id', $mandor->id)->first();

    $this->actingAs($mandor)
        ->patchJson('/api/v1/sub-companies/' . $subCompany->uuid, [
            'name' => 'Should Fail',
        ])
        ->assertForbidden();
});

it('creates sub company with kepala mandor role', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor Kepala',
                'phone' => '081234567899',
                'email' => 'kepala@test.com',
                'address' => 'Jl. Kepala No. 1',
                'role' => 'KEPALA_MANDOR',
            ],
            'sub_company' => [
                'name' => 'Cabang Kepala',
                'address' => 'Jl. Cabang Kepala No. 2',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $mandor = User::where('phone', '081234567899')->first();
    expect($mandor->role)->toBe(Role::KEPALA_MANDOR);
});

it('defaults mandor role to MANDOR when not specified', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor Biasa',
                'phone' => '081234567898',
            ],
            'sub_company' => [
                'name' => 'Cabang Biasa',
            ],
        ]);

    $response->assertCreated();

    $mandor = User::where('phone', '081234567898')->first();
    expect($mandor->role)->toBe(Role::MANDOR);
});

it('rejects invalid mandor role value', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/sub-companies', [
            'mandor' => [
                'name' => 'Mandor Invalid',
                'phone' => '081234567897',
                'role' => 'SUPERADMIN',
            ],
            'sub_company' => [
                'name' => 'Cabang Invalid',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mandor.role']);
});
