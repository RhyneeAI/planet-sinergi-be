<?php

use App\Enums\OpsExpenseType;
use App\Enums\OpsSourceType;
use App\Models\Company;
use App\Models\OpsTransferConfirmation;
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
    $this->otherMandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);
    $this->subCompany = SubCompany::where('mandor_id', $this->mandor->id)->first();
});

function createPendingTransferConfirmation(User $admin, User $mandor, SubCompany $subCompany): OpsTransferConfirmation
{
    $response = test()->actingAs($admin)
        ->post('/api/v1/operational/expenses', [
            'expense_type' => OpsExpenseType::MANDOR->value,
            'mandor_uuid' => $mandor->uuid,
            'sub_company_uuid' => $subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json']);

    $response->assertCreated();

    return OpsTransferConfirmation::first();
}

it('lists transfer confirmation for mandor managing the sub company branch', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/transfer-confirmations')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.uuid', $confirmation->uuid);
});

it('hides transfer confirmation from mandor when filtered by another sub company', function () {
  createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $otherSubCompany = SubCompany::where('mandor_id', $this->otherMandor->id)->first();

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/transfer-confirmations?sub_company_uuid=' . $otherSubCompany->uuid)
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('allows mandor to confirm transfer assigned to their branch', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/transfer-confirmations/' . $confirmation->uuid . '/confirm', [
            'confirmed_amount' => 250000,
            'mandor_proof_files' => [UploadedFile::fake()->create('mandor-proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'CONFIRMED')
        ->assertJsonPath('data.confirmed_amount', 250000);
});

it('allows mandor to confirm transfer with adjustable received amount', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/transfer-confirmations/' . $confirmation->uuid . '/confirm', [
            'confirmed_amount' => 235000,
            'mandor_proof_files' => [UploadedFile::fake()->create('mandor-proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CONFIRMED')
        ->assertJsonPath('data.confirmed_amount', 235000);

    expect((float) $this->subCompany->fresh()->wallet?->balance)->toBe(235000.0);
});

it('forbids another mandor from confirming transfer', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->actingAs($this->otherMandor)
        ->post('/api/v1/operational/transfer-confirmations/' . $confirmation->uuid . '/confirm', [
            'confirmed_amount' => 250000,
            'mandor_proof_files' => [UploadedFile::fake()->create('mandor-proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertForbidden()
        ->assertJsonPath('message', __('operational.confirmations.not_accessible'));
});

it('allows current branch mandor to confirm after sub company reassignment', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->subCompany->update(['mandor_id' => $this->otherMandor->id]);

    $this->actingAs($this->otherMandor)
        ->post('/api/v1/operational/transfer-confirmations/' . $confirmation->uuid . '/confirm', [
            'confirmed_amount' => 250000,
            'mandor_proof_files' => [UploadedFile::fake()->create('mandor-proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'CONFIRMED');
});

it('creates income visible in incomes index after confirm transfer', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->actingAs($this->mandor)
        ->post('/api/v1/operational/transfer-confirmations/' . $confirmation->uuid . '/confirm', [
            'confirmed_amount' => 250000,
            'mandor_proof_files' => [UploadedFile::fake()->create('mandor-proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/incomes?mandor_uuid=' . $this->mandor->uuid);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source_type', OpsSourceType::MANDOR->value)
        ->assertJsonPath('data.0.name', 'Transfer Dana')
        ->assertJsonPath('data.0.mandor.uuid', $this->mandor->uuid);

    $responseAll = $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/incomes');

    $responseAll->assertOk()
        ->assertJsonFragment(['name' => 'Transfer Dana']);

    $responseMandor = $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/incomes');

    $responseMandor->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source_type', OpsSourceType::MANDOR->value)
        ->assertJsonPath('data.0.name', 'Transfer Dana');
});

it('still lists transfer for original mandor after sub company reassignment', function () {
    $confirmation = createPendingTransferConfirmation($this->admin, $this->mandor, $this->subCompany);

    $this->subCompany->update(['mandor_id' => $this->otherMandor->id]);

    $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/transfer-confirmations')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $confirmation->uuid);
});
