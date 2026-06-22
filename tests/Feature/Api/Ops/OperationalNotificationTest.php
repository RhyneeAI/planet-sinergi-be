<?php

use App\Enums\OpsExpenseType;
use App\Models\Company;
use App\Models\OpsNotification;
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
    $this->subCompany = SubCompany::where('mandor_id', $this->mandor->id)->first();
});

function createMandorPendingNotification(User $admin, User $mandor, SubCompany $subCompany): array
{
    test()->actingAs($admin)
        ->post('/api/v1/operational/expenses', [
            'expense_type' => OpsExpenseType::MANDOR->value,
            'mandor_uuid' => $mandor->uuid,
            'sub_company_uuid' => $subCompany->uuid,
            'name' => 'Transfer Dana',
            'amount' => 250000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $confirmation = OpsTransferConfirmation::first();
    $notification = OpsNotification::where('user_id', $mandor->id)->first();

    return compact('confirmation', 'notification');
}

it('lists notifications quickly with action target uuid', function () {
    ['notification' => $notification, 'confirmation' => $confirmation] = createMandorPendingNotification(
        $this->admin,
        $this->mandor,
        $this->subCompany
    );

    $response = $this->actingAs($this->mandor)
        ->getJson('/api/v1/operational/notifications');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.uuid', $notification->uuid)
        ->assertJsonPath('data.0.action.target_uuid', $confirmation->uuid)
        ->assertJsonPath('data.0.action.transfer_confirmation', null);
});

it('marks notification as read using notification uuid', function () {
    ['notification' => $notification] = createMandorPendingNotification(
        $this->admin,
        $this->mandor,
        $this->subCompany
    );

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/notifications/' . $notification->uuid . '/read')
        ->assertOk()
        ->assertJsonPath('data.is_read', true);
});

it('marks notification as read using linked transfer confirmation uuid', function () {
    ['notification' => $notification, 'confirmation' => $confirmation] = createMandorPendingNotification(
        $this->admin,
        $this->mandor,
        $this->subCompany
    );

    $this->actingAs($this->mandor)
        ->patchJson('/api/v1/operational/notifications/' . $confirmation->uuid . '/read')
        ->assertOk()
        ->assertJsonPath('data.uuid', $notification->uuid)
        ->assertJsonPath('data.is_read', true);
});

it('returns not found when another user tries to read notification', function () {
    ['notification' => $notification] = createMandorPendingNotification(
        $this->admin,
        $this->mandor,
        $this->subCompany
    );

    $otherMandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($otherMandor)
        ->patchJson('/api/v1/operational/notifications/' . $notification->uuid . '/read')
        ->assertNotFound()
        ->assertJsonPath('message', __('operational.notifications.not_found'));
});
