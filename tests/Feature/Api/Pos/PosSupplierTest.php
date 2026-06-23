<?php

use App\Models\PosSupplier;
use App\Models\User;
use App\Models\Company;
use App\Models\PosPurchaseTransaction;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get supplier list', function () {
    PosSupplier::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'name', 'address', 'phone']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns suppliers belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    PosSupplier::factory(3)->create(['company_id' => $otherCompany->id]);
    PosSupplier::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can filter suppliers by search name', function () {
    PosSupplier::factory()->create(['name' => 'Laptop Gaming', 'company_id' => $this->company->id]);
    PosSupplier::factory()->create(['name' => 'Mouse Wireless', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers?search=laptop');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Laptop Gaming');
});

it('can sort suppliers by name', function () {
    PosSupplier::factory()->create(['name' => 'Zebra', 'company_id' => $this->company->id]);
    PosSupplier::factory()->create(['name' => 'Apple', 'company_id' => $this->company->id]);
    PosSupplier::factory()->create(['name' => 'Banana', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers?order_by_key=name&order_by_value=asc');

    expect($response->json('data.0.name'))->toBe('Apple');
    expect($response->json('data.1.name'))->toBe('Banana');
    expect($response->json('data.2.name'))->toBe('Zebra');
});

it('can paginate suppliers with custom per_page', function () {
    PosSupplier::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/suppliers')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a supplier', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', [
            'name'    => 'PT Maju Jaya',
            'address' => 'Jl. Sudirman No. 1',
            'phone'   => '08123456789',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'PT Maju Jaya');
});

it('can create a supplier without optional fields', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', ['name' => 'PT Maju Jaya'])
        ->assertStatus(201)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.phone', null);
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', ['name' => str_repeat('a', 256)])
        ->assertStatus(422);
});

it('returns 422 when name is duplicate within same company', function () {
    PosSupplier::factory()->create([
        'name'       => 'PT Maju Jaya',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', ['name' => 'PT Maju Jaya'])
        ->assertStatus(422);
});

it('allows same supplier name in different companies', function () {
    $otherCompany = Company::factory()->create();
    PosSupplier::factory()->create([
        'name'       => 'PT Maju Jaya',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', ['name' => 'PT Maju Jaya'])
        ->assertStatus(201);
});

it('returns 422 when phone format is invalid', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', [
            'name'  => 'PT Maju Jaya',
            'phone' => 'abc-invalid',
        ])
        ->assertStatus(422);
});

it('returns 422 when phone exceeds 20 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/suppliers', [
            'name'  => 'PT Maju Jaya',
            'phone' => '081234567890123456789',
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/suppliers', ['name' => 'PT Maju Jaya'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get supplier detail', function () {
    $supplier = PosSupplier::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $supplier->uuid);
});

it('returns 404 when supplier not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/suppliers/uuid-tidak-ada')
        ->assertStatus(404);
});

it('returns 404 when accessing supplier from other company', function () {
    $otherCompany = Company::factory()->create();
    $supplier     = PosSupplier::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a supplier', function () {
    $supplier = PosSupplier::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/suppliers/{$supplier->uuid}", ['name' => 'PT Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'PT Updated');
});

it('can partial update (PATCH) supplier without sending all fields', function () {
    $supplier = PosSupplier::factory()->create([
        'name'       => 'PT Original',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/suppliers/{$supplier->uuid}", [])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'PT Original');
});

it('can update only address without affecting other fields', function () {
    $supplier = PosSupplier::factory()->create([
        'name'       => 'PT Original',
        'phone'      => '08123456789',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/suppliers/{$supplier->uuid}", [
            'address' => 'Jl. Baru No. 99',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'PT Original')
        ->assertJsonPath('data.phone', '08123456789')
        ->assertJsonPath('data.address', 'Jl. Baru No. 99');
});

it('returns 422 when updating with duplicate name', function () {
    PosSupplier::factory()->create([
        'name'       => 'PT Maju Jaya',
        'company_id' => $this->company->id,
    ]);

    $supplier = PosSupplier::factory()->create([
        'name'       => 'PT Sejahtera',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/suppliers/{$supplier->uuid}", ['name' => 'PT Maju Jaya'])
        ->assertStatus(422);
});

it('returns 404 when updating supplier from other company', function () {
    $otherCompany = Company::factory()->create();
    $supplier     = PosSupplier::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/suppliers/{$supplier->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent supplier', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/suppliers/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a supplier', function () {
    $supplier = PosSupplier::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosSupplier::withTrashed()->find($supplier->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting supplier that has purchase transactions', function () {
    $supplier = PosSupplier::factory()->create(['company_id' => $this->company->id]);

    PosPurchaseTransaction::factory()->create([
        'supplier_id' => $supplier->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting supplier from other company', function () {
    $otherCompany = Company::factory()->create();
    $supplier     = PosSupplier::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent supplier', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/suppliers/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $supplier = PosSupplier::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/suppliers/{$supplier->uuid}")
        ->assertStatus(401);
});