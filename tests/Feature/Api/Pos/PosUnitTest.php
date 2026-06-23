<?php

use App\Models\PosUnit;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Company;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get unit list', function () {
    PosUnit::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/units')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'name']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns units belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    PosUnit::factory(3)->create(['company_id' => $otherCompany->id]);
    PosUnit::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/units');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can paginate units with custom per_page', function () {
    PosUnit::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/units?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/units')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a unit', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/units', ['name' => 'Pcs'])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Pcs');
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/units', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/units', ['name' => str_repeat('a', 256)])
        ->assertStatus(422);
});

it('returns 422 when name is duplicate within same company', function () {
    PosUnit::factory()->create([
        'name'       => 'Pcs',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/units', ['name' => 'Pcs'])
        ->assertStatus(422);
});

it('allows same unit name in different companies', function () {
    $otherCompany = Company::factory()->create();
    PosUnit::factory()->create([
        'name'       => 'Pcs',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/units', ['name' => 'Pcs'])
        ->assertStatus(201);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/units', ['name' => 'Pcs'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get unit detail', function () {
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $unit->uuid);
});

it('returns 404 when unit not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/units/uuid-tidak-ada')
        ->assertStatus(404);
});

it('returns 404 when accessing unit from other company', function () {
    $otherCompany = Company::factory()->create();
    $unit         = PosUnit::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a unit', function () {
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/units/{$unit->uuid}", ['name' => 'Lusin'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Lusin');
});

it('can partial update (PATCH) unit without sending all fields', function () {
    $unit = PosUnit::factory()->create([
        'name'       => 'Original',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/units/{$unit->uuid}", [])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Original');
});

it('returns 422 when updating with duplicate name', function () {
    PosUnit::factory()->create([
        'name'       => 'Pcs',
        'company_id' => $this->company->id,
    ]);

    $unit = PosUnit::factory()->create([
        'name'       => 'Kg',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/units/{$unit->uuid}", ['name' => 'Pcs'])
        ->assertStatus(422);
});

it('returns 404 when updating unit from other company', function () {
    $otherCompany = Company::factory()->create();
    $unit         = PosUnit::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/units/{$unit->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent unit', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/units/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a unit', function () {
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosUnit::withTrashed()->find($unit->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting unit that has products', function () {
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    PosProduct::factory()->create([
        'unit_id'    => $unit->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting unit from other company', function () {
    $otherCompany = Company::factory()->create();
    $unit         = PosUnit::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent unit', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/units/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/units/{$unit->uuid}")
        ->assertStatus(401);
});