<?php

use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get customer type list', function () {
    PosCustomerType::factory(5)->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customer-types')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'type', 'discount']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns customer types belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);

    PosCustomerType::factory(3)->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);
    PosCustomerType::factory(2)->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customer-types');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can paginate customer types with custom per_page', function () {
    PosCustomerType::factory(20)->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customer-types?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('can search customer types by type name', function () {
    $vipCustomerType = PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    PosCustomerType::factory()->create([
        'type'       => 'Regular',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customer-types?search=vip');
        
        
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.type'))->toBe('VIP');
    expect($response->json('data.0.uuid'))->toBe($vipCustomerType->uuid);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/pos/customer-types')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a customer type', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', [
            'type'     => 'VIP',
            'discount' => 10,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.type', 'VIP')
        ->assertJsonPath('data.discount', 10);
});

it('can create customer type without discount (defaults to 0)', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => 'Regular'])
        ->assertStatus(201)
        ->assertJsonPath('data.discount', 0);
});

it('returns 422 when type is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when type exceeds 255 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => str_repeat('a', 256)])
        ->assertStatus(422);
});

it('returns 422 when type is duplicate within same company', function () {
    PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => 'VIP'])
        ->assertStatus(422);
});

it('allows same type name in different companies', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);

    PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => 'VIP'])
        ->assertStatus(201);
});

it('allows same type name if other company deleted', function () {
    // Buat customer type dengan company sendiri, lalu soft delete
    $customerType = PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    
    // Soft delete
    $customerType->delete();
    
    // Harus bisa create dengan type yang sama karena sudah dihapus (tanpaTrashed)
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => 'VIP'])
        ->assertStatus(201);
});

it('prevents duplicate type name if still exists (not deleted)', function () {
    // Buat customer type aktif
    PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    
    // Harus gagal karena type VIP sudah ada (belum dihapus)
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', ['type' => 'VIP'])
        ->assertStatus(422);
});

it('returns 422 when discount is negative', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', [
            'type'     => 'VIP',
            'discount' => -1,
        ])
        ->assertStatus(422);
});

it('returns 422 when discount exceeds 100', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customer-types', [
            'type'     => 'VIP',
            'discount' => 101,
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/pos/customer-types', ['type' => 'VIP'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get customer type detail', function () {
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $customerType->uuid);
});

it('returns 404 when customer type not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customer-types/uuid-tidak-ada')
        ->assertStatus(404);
});

it('returns 404 when accessing customer type from other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a customer type', function () {
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customer-types/{$customerType->uuid}", [
            'type'     => 'Updated',
            'discount' => 15,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.type', 'Updated')
        ->assertJsonPath('data.discount', 15);
});

it('can partial update (PATCH) without sending all fields', function () {
    $customerType = PosCustomerType::factory()->create([
        'type'       => 'Original',
        'discount'   => 5,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customer-types/{$customerType->uuid}", [])
        ->assertStatus(200)
        ->assertJsonPath('data.type', 'Original')
        ->assertJsonPath('data.discount', 5);
});

it('returns 422 when updating with duplicate type', function () {
    PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $customerType = PosCustomerType::factory()->create([
        'type'       => 'Regular',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customer-types/{$customerType->uuid}", ['type' => 'VIP'])
        ->assertStatus(422);
});

it('returns 404 when updating customer type from other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customer-types/{$customerType->uuid}", ['type' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent customer type', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/pos/customer-types/invalid-uuid', ['type' => 'New'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a customer type', function () {
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosCustomerType::withTrashed()->find($customerType->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting customer type that has customers', function () {
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    PosCustomer::factory()->create([
        'customer_type_id' => $customerType->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting customer type from other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent customer type', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/pos/customer-types/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->deleteJson("/api/v1/pos/customer-types/{$customerType->uuid}")
        ->assertStatus(401);
});