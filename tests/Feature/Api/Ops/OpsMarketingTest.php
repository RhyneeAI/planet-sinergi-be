<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\MarketingLeadMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

it('lists commission marketings for ops', function () {
    User::factory()->marketingLead()->create(['company_id' => $this->company->id, 'name' => 'Lead A']);
    User::factory()->marketing()->create(['company_id' => $this->company->id, 'name' => 'Marketing A']);
    User::factory()->marketingTetap()->create(['company_id' => $this->company->id, 'name' => 'Tetap A']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/marketings')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');
});

it('creates marketing lead without leader assignment', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/marketings', [
            'name' => 'Lead Satu',
            'phone' => '081111111111',
            'role' => Role::MARKETING_LEAD->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', Role::MARKETING_LEAD->value)
        ->assertJsonPath('data.can_login', false)
        ->assertJsonPath('data.leader', null);
});

it('requires leader when creating marketing member', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/marketings', [
            'name' => 'Marketing Tanpa Lead',
            'phone' => '081222222222',
            'role' => Role::MARKETING->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['leader_uuid']);
});

it('creates marketing member assigned to marketing lead', function () {
    $lead = User::factory()->marketingLead()->create([
        'company_id' => $this->company->id,
        'name' => 'Lead Dua',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/marketings', [
            'name' => 'Marketing Bawah',
            'phone' => '081333333333',
            'role' => Role::MARKETING->value,
            'leader_uuid' => $lead->uuid,
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', Role::MARKETING->value)
        ->assertJsonPath('data.leader.uuid', $lead->uuid);

    expect(MarketingLeadMember::count())->toBe(1);
});

it('shows marketing lead with members on detail', function () {
    $lead = User::factory()->marketingLead()->create([
        'company_id' => $this->company->id,
        'name' => 'Lead Tiga',
    ]);

    $member = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'name' => 'Anggota Satu',
    ]);

    MarketingLeadMember::create([
        'marketing_id' => $member->id,
        'leader_id' => $lead->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/marketings/' . $lead->uuid)
        ->assertOk()
        ->assertJsonPath('data.members.0.uuid', $member->uuid)
        ->assertJsonPath('data.members_count', 1);
});

it('updates marketing member leader assignment', function () {
    $oldLead = User::factory()->marketingLead()->create(['company_id' => $this->company->id]);
    $newLead = User::factory()->marketingLead()->create(['company_id' => $this->company->id]);
    $member = User::factory()->marketing()->create(['company_id' => $this->company->id]);

    MarketingLeadMember::create([
        'marketing_id' => $member->id,
        'leader_id' => $oldLead->id,
    ]);

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/operational/marketings/' . $member->uuid, [
            'leader_uuid' => $newLead->uuid,
        ])
        ->assertOk()
        ->assertJsonPath('data.leader.uuid', $newLead->uuid);
});

it('prevents deleting marketing lead that still has members', function () {
    $lead = User::factory()->marketingLead()->create(['company_id' => $this->company->id]);
    $member = User::factory()->marketing()->create(['company_id' => $this->company->id]);

    MarketingLeadMember::create([
        'marketing_id' => $member->id,
        'leader_id' => $lead->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/operational/marketings/' . $lead->uuid)
        ->assertUnprocessable()
        ->assertJsonPath('message', __('operational.marketings.has_members'));
});

it('rejects employee creation for commission marketing roles', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/employees', [
            'name' => 'Should Fail',
            'phone' => '081999999999',
            'password' => 'password123',
            'role' => Role::MARKETING->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('allows employee creation for marketing tetap', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/operational/employees', [
            'name' => 'Marketing Tetap',
            'phone' => '081888888888',
            'password' => 'password123',
            'role' => Role::MARKETING_TETAP->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', Role::MARKETING_TETAP->value)
        ->assertJsonPath('data.can_login', true);
});

it('updates marketing password when provided', function () {
    $lead = User::factory()->marketingLead()->create([
        'company_id' => $this->company->id,
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/operational/marketings/' . $lead->uuid, [
            'password' => 'new-password',
        ])
        ->assertOk();

    expect(Hash::check('new-password', $lead->fresh()->password))->toBeTrue();
});
