<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\MarketingLeadMember;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

it('can get pos marketing picker list including marketing tetap', function () {
    User::factory()->marketingLead()->create(['company_id' => $this->company->id]);
    User::factory()->marketing()->create(['company_id' => $this->company->id]);
    User::factory()->marketingTetap()->create(['company_id' => $this->company->id]);
    User::factory()->kasir()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/marketings')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('returns marketing role and leader on pos marketing detail', function () {
    $lead = User::factory()->marketingLead()->create([
        'company_id' => $this->company->id,
        'name' => 'Lead POS',
    ]);

    $member = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'name' => 'Member POS',
    ]);

    MarketingLeadMember::create([
        'marketing_id' => $member->id,
        'leader_id' => $lead->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/marketings/' . $member->uuid)
        ->assertOk()
        ->assertJsonPath('data.role', Role::MARKETING->value)
        ->assertJsonPath('data.leader.uuid', $lead->uuid);
});

it('returns 401 when not authenticated on pos marketing index', function () {
    $this->getJson('/api/v1/pos/marketings')->assertUnauthorized();
});

it('does not expose pos write routes for marketings', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/marketings', ['name' => 'Test'])
        ->assertStatus(405);
});
