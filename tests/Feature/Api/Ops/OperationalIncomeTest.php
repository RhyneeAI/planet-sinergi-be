<?php

use App\Enums\OpsExpenseType;
use App\Enums\OpsSourceType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\OpsExpense;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Models\OpsWallet;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);
    $this->subCompany = SubCompany::where('mandor_id', $this->mandor->id)->first();
});

it('stores admin income without mandor as internal pusat income', function () {
    $response = $this->actingAs($this->admin)
        ->post('/api/v1/operational/incomes', [
            'name' => 'Pemasukan Pusat',
            'amount' => 500000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source_type', OpsSourceType::INTERNAL->value)
        ->assertJsonPath('data.mandor', null);

    expect(OpsTransferConfirmation::count())->toBe(0);
});

it('stores admin income with optional mandor attribution', function () {
    $response = $this->actingAs($this->admin)
        ->post('/api/v1/operational/incomes', [
            'mandor_uuid' => $this->mandor->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Atribusi Cabang',
            'amount' => 200000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.source_type', OpsSourceType::INTERNAL->value)
        ->assertJsonPath('data.mandor.uuid', $this->mandor->uuid);

    expect(OpsTransferConfirmation::count())->toBe(0);
});

it('stores admin internal expense without mandor', function () {
    $response = $this->actingAs($this->admin)
        ->post('/api/v1/operational/expenses', [
            'expense_type' => OpsExpenseType::INTERNAL->value,
            'name' => 'Pengeluaran Pusat',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.expense_type', OpsExpenseType::INTERNAL->value)
        ->assertJsonPath('data.mandor', null);

    expect(OpsExpense::count())->toBe(1);
    expect(OpsTransferConfirmation::count())->toBe(0);
});

it('stores admin mandor transfer expense with pending income confirmation', function () {
    $response = $this->actingAs($this->admin)
        ->post('/api/v1/operational/expenses', [
            'expense_type' => OpsExpenseType::MANDOR->value,
            'mandor_uuid' => $this->mandor->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.expense_type', OpsExpenseType::MANDOR->value)
        ->assertJsonPath('data.transfer_income.source_type', OpsSourceType::MANDOR->value)
        ->assertJsonPath('data.transfer_income.transfer_confirmation.status', 'PENDING');

    expect(OpsTransferConfirmation::count())->toBe(1);
});

it('stores mandor income with only sub company uuid and credits wallet', function () {
    $wallet = OpsWallet::where('sub_company_id', $this->subCompany->id)->first();
    expect((float) $wallet->balance)->toBe(0.0);

    $response = $this->actingAs($this->mandor)
        ->post('/api/v1/operational/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Cabang',
            'amount' => 150000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source_type', OpsSourceType::INTERNAL->value);

    expect(OpsIncome::where('source_type', OpsSourceType::INTERNAL)->count())->toBe(1);
    expect((float) $wallet->fresh()->balance)->toBe(150000.0);
    expect(OpsTransferConfirmation::count())->toBe(0);
});

it('stores mandor income with up to three proof images', function () {
    $response = $this->actingAs($this->mandor)
        ->post('/api/v1/operational/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Multi Bukti',
            'amount' => 90000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [
                UploadedFile::fake()->create('proof-1.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('proof-2.jpg', 100, 'image/jpeg'),
                UploadedFile::fake()->create('proof-3.jpg', 100, 'image/jpeg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonCount(3, 'data.proof_files');

        expect(OpsIncome::first()->proof_files)->toHaveCount(3);
});

it('allows mandor to update own internal branch income', function () {
    $incomeResponse = $this->actingAs($this->mandor)
        ->post('/api/v1/operational/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Cabang',
            'amount' => 150000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $incomeResponse->assertCreated();
    $income = OpsIncome::first();

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/incomes/' . $income->uuid, [
            'name' => 'Pemasukan Cabang Updated',
            'amount' => 175000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi nominal',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pemasukan Cabang Updated')
        ->assertJsonPath('data.amount', 175000);
});

it('allows mandor to update income by record mandor id after branch reassignment', function () {
    app(\App\Services\Operational\OpsWalletService::class)
        ->getOrCreateWallet($this->mandor, $this->subCompany);

    $income = OpsIncome::create([
        'name' => 'Pemasukan Lama',
        'amount' => 120000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'source_type' => OpsSourceType::INTERNAL,
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'created_by' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);

    $otherMandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $this->subCompany->update(['mandor_id' => $otherMandor->id]);

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/incomes/' . $income->uuid, [
            'name' => 'Pemasukan Lama Updated',
            'amount' => 120000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pemasukan Lama Updated');
});

it('allows current branch mandor to update income after branch reassignment', function () {
    app(\App\Services\Operational\OpsWalletService::class)
        ->getOrCreateWallet($this->mandor, $this->subCompany);

    $income = OpsIncome::create([
        'name' => 'Pemasukan Cabang',
        'amount' => 90000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'source_type' => OpsSourceType::INTERNAL,
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'created_by' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);

    $otherMandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $this->subCompany->update(['mandor_id' => $otherMandor->id]);

    $this->actingAs($otherMandor)
        ->patchJson('/api/v1/operational/incomes/' . $income->uuid, [
            'name' => 'Pemasukan Cabang Updated',
            'amount' => 90000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pemasukan Cabang Updated');
});

it('forbids mandor from editing admin transfer income', function () {
    $income = OpsIncome::create([
        'name' => 'Transfer Admin',
        'amount' => 100000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'source_type' => OpsSourceType::MANDOR,
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'created_by' => $this->admin->id,
        'company_id' => $this->company->id,
    ]);

    OpsTransferConfirmation::create([
        'confirmable_type' => $income->getMorphClass(),
        'confirmable_id' => $income->id,
        'status' => 'PENDING',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/incomes/' . $income->uuid, [
            'name' => 'Updated',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.incomes.not_editable'));
});

it('returns empty array when income show uuid is not found', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/incomes/' . Str::uuid())
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', []);
});

it('stores income with transfer payment method', function () {
    $this->actingAs($this->admin)
        ->post('/api/v1/operational/incomes', [
            'name' => 'Pemasukan Transfer',
            'amount' => 300000,
            'date' => now()->toDateString(),
            'payment_method' => 'TRANSFER',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'TRANSFER');
});

it('allows income backdate up to H-3', function () {
    $this->actingAs($this->admin)
        ->post('/api/v1/operational/incomes', [
            'name' => 'Pemasukan H-3',
            'amount' => 100000,
            'date' => now()->subDays(3)->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();
});

it('rejects income backdate beyond H-3', function () {
    $this->actingAs($this->admin)
        ->post('/api/v1/operational/incomes', [
            'name' => 'Pemasukan Terlalu Lama',
            'amount' => 100000,
            'date' => now()->subDays(4)->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.incomes.store_window_expired', ['days' => 3]));
});

it('rejects income edit after H+3 from creation', function () {
    $income = OpsIncome::create([
        'name' => 'Pemasukan Lama',
        'amount' => 100000,
        'date' => now()->toDateString(),
        'payment_method' => 'CASH',
        'proof_files' => ['proofs/test.jpg'],
        'source_type' => OpsSourceType::INTERNAL,
        'created_by' => $this->admin->id,
        'company_id' => $this->company->id,
    ]);

    $income->forceFill(['created_at' => now()->subDays(4)])->save();

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/operational/incomes/' . $income->uuid, [
            'name' => 'Pemasukan Lama',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.incomes.edit_window_expired', ['days' => 3]));
});

it('includes sub companies in admin dashboard', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/dashboard/admin');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'total_income',
                'total_expense',
                'sub_companies' => [
                    ['uuid', 'name', 'code', 'total_income', 'total_expense'],
                ],
            ],
        ]);
});
