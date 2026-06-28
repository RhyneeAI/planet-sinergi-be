<?php

use App\Models\Company;
use App\Models\User;
use App\Enums\Role;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get marketing list', function () {
    User::factory(5)->marketing()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/marketings')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'name', 'phone', 'email', 'role']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns marketings belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    User::factory(3)->marketing()->create(['company_id' => $otherCompany->id]);

    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/marketings');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('does not return non-marketing users', function () {
    User::factory()->superAdmin()->create(['company_id' => $this->company->id]);
    User::factory()->owner()->create(['company_id' => $this->company->id]);
    
    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/marketings');

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.role'))->toBe(Role::MARKETING->value);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/marketings')->assertStatus(401);
});

it('can filter marketings by search (name, phone, email)', function () {
    User::factory()->marketing()->create([
        'name'     => 'Budi Santoso',
        'phone'    => '081234567890', 
        'email'    => 'budi@example.com',
        'company_id' => $this->company->id,
    ]);
    User::factory()->marketing()->create([
        'name'     => 'Siti Aminah',
        'phone'    => '081234567891',
        'email'    => 'siti@example.com',
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/marketings?search=budi');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Budi Santoso');
});

it('can sort marketings by name', function () {
    User::factory()->marketing()->create(['name' => 'Zulkarnain', 'company_id' => $this->company->id]);
    User::factory()->marketing()->create(['name' => 'Agus', 'company_id' => $this->company->id]);
    User::factory()->marketing()->create(['name' => 'Budi', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/marketings?order_by_key=name&order_by_value=asc');

    expect($response->json('data.0.name'))->toBe('Agus');
    expect($response->json('data.1.name'))->toBe('Budi');
    expect($response->json('data.2.name'))->toBe('Zulkarnain');
});

it('can paginate marketings with custom per_page', function () {
    User::factory(20)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/marketings?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

// =============================
// SHOW
// =============================

it('can get marketing detail', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'name'       => 'Detail Marketing'
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Detail Marketing')
        ->assertJsonPath('data.role', Role::MARKETING->value);
});

it('returns 404 when marketing not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/marketings/invalid-uuid')
        ->assertStatus(404);
});

it('returns 404 when accessing marketing from other company', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(404);
});

// =============================
// STORE — READ-ONLY (405)
// =============================

it('returns 405 when trying to store marketing via POS', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', [
            'name'    => 'Ahmad Fauzi',
            'email'   => 'ahmad@example.com',
            'phone'   => '08123456789',
        ])
        ->assertStatus(405);
});

// =============================
// UPDATE — READ-ONLY (405)
// =============================

it('returns 405 when trying to update marketing via POS', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/marketings/{$marketing->uuid}", [
            'name' => 'Updated Name',
        ])
        ->assertStatus(405);
});

// =============================
// DESTROY — READ-ONLY (405)
// =============================

it('returns 405 when trying to delete marketing via POS', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(405);
});
