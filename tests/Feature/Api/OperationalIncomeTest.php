<?php

use App\Enums\OpsSourceType;
use App\Enums\Role;
use App\Models\Company;
use App\Models\OpsIncome;
use App\Models\OpsTransferConfirmation;
use App\Models\OpsWallet;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('requires mandor and sub company uuid for admin income store', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/admin/incomes', [
            'mandor_uuid' => $this->mandor->uuid,
            'name' => 'Transfer Dana',
            'amount' => 100000,
            'date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sub_company_uuid', 'proof_file']);
});

it('stores admin income transfer with pending confirmation', function () {
    $response = $this->actingAs($this->admin)
        ->post('/api/v1/operational/admin/incomes', [
            'mandor_uuid' => $this->mandor->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source_type', OpsSourceType::MANDOR->value)
        ->assertJsonPath('data.transfer_confirmation.status', 'PENDING');

    expect(OpsTransferConfirmation::count())->toBe(1);
});

it('stores mandor income with only sub company uuid and credits wallet', function () {
    $wallet = OpsWallet::where('sub_company_id', $this->subCompany->id)->first();
    expect((float) $wallet->balance)->toBe(0.0);

    $response = $this->actingAs($this->mandor)
        ->post('/api/v1/operational/mandor/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Cabang',
            'amount' => 150000,
            'date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source_type', OpsSourceType::INTERNAL->value);

    expect(OpsIncome::where('source_type', OpsSourceType::INTERNAL)->count())->toBe(1);
    expect((float) $wallet->fresh()->balance)->toBe(150000.0);
    expect(OpsTransferConfirmation::count())->toBe(0);
});

it('forbids mandor from admin income endpoint', function () {
    $this->actingAs($this->mandor)
        ->postJson('/api/v1/operational/admin/incomes', [
            'mandor_uuid' => $this->mandor->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 100000,
            'date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('forbids admin from mandor income endpoint', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/mandor/incomes', [
            'sub_company_uuid' => $this->subCompany->uuid,
            'name' => 'Pemasukan Cabang',
            'amount' => 100000,
            'date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('forbids mandor from editing admin transfer income', function () {
    $income = OpsIncome::create([
        'name' => 'Transfer Admin',
        'amount' => 100000,
        'date' => now()->toDateString(),
        'proof_file' => 'proofs/test.jpg',
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
        ->patchJson('/api/v1/operational/mandor/incomes/' . $income->uuid, [
            'name' => 'Updated',
            'amount' => 100000,
            'date' => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('operational.incomes.not_editable'));
});
