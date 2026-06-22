<?php

use App\Enums\OpsExpenseType;
use App\Models\Company;
use App\Models\OpsExpense;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);
    $this->subCompany = SubCompany::where('mandor_id', $this->mandor->id)->first();
});

it('allows mandor to update own internal branch expense', function () {
    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Saldo Awal',
            'amount' => 500000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $response = $this->actingAs($this->mandor)
        ->post('/api/v1/operational/expenses', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pengeluaran Cabang',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated();
    $expense = OpsExpense::first();

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/expenses/' . $expense->uuid, [
            'name' => 'Pengeluaran Cabang Updated',
            'amount' => 120000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi nominal',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pengeluaran Cabang Updated')
        ->assertJsonPath('data.amount', 120000);
});

it('allows mandor to update expense by record mandor id even after branch reassignment', function () {
    app(\App\Services\Operational\OpsWalletService::class)
        ->getOrCreateWallet($this->mandor, $this->subCompany);

    $expense = OpsExpense::create([
        'name' => 'Pengeluaran Lama',
        'amount' => 80000,
        'date' => now()->toDateString(),
        'proof_files' => ['proofs/test.jpg'],
        'expense_type' => OpsExpenseType::INTERNAL,
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
        ->patchJson('/api/v1/operational/expenses/' . $expense->uuid, [
            'name' => 'Pengeluaran Lama Updated',
            'amount' => 80000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pengeluaran Lama Updated');
});

it('forbids mandor from updating admin transfer expense', function () {
    $admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($admin)
        ->post('/api/v1/operational/expenses', [
            'expense_type' => OpsExpenseType::MANDOR->value,
            'mandor_uuid' => $this->mandor->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $expense = OpsExpense::where('expense_type', OpsExpenseType::MANDOR)->first();

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/expenses/' . $expense->uuid, [
            'name' => 'Should Fail',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Test',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.expenses.not_editable'));
});

it('rejects expense backdate beyond H-1', function () {
    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/expenses', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pengeluaran Terlalu Lama',
            'amount' => 50000,
            'date' => now()->subDays(2)->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.expenses.store_window_expired', ['days' => 1]));
});

it('allows expense backdate on H-1', function () {
    app(\App\Services\Operational\OpsWalletService::class)
        ->getOrCreateWallet($this->mandor, $this->subCompany);

    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Saldo',
            'amount' => 200000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/expenses', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pengeluaran H-1',
            'amount' => 50000,
            'date' => now()->subDay()->toDateString(),
            'payment_method' => 'TRANSFER',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'TRANSFER');
});

it('rejects expense edit after H+1 from creation', function () {
    app(\App\Services\Operational\OpsWalletService::class)
        ->getOrCreateWallet($this->mandor, $this->subCompany);

    $expense = OpsExpense::create([
        'name' => 'Pengeluaran Lama',
        'amount' => 50000,
        'date' => now()->toDateString(),
        'payment_method' => 'CASH',
        'proof_files' => ['proofs/test.jpg'],
        'expense_type' => OpsExpenseType::INTERNAL,
        'mandor_id' => $this->mandor->id,
        'sub_company_id' => $this->subCompany->id,
        'created_by' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);

    $expense->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/expenses/' . $expense->uuid, [
            'name' => 'Pengeluaran Lama',
            'amount' => 50000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'reason' => 'Koreksi',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.expenses.edit_window_expired', ['days' => 1]));
});
