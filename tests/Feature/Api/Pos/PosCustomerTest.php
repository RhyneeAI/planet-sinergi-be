<?php

use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use App\Models\PosSalesTransaction;
use App\Models\User;
use App\Models\Company;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->user         = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
    $this->customerType = PosCustomerType::factory()->create([
        'type'       => 'Regular',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get customer list', function () {
    PosCustomer::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers')
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

it('only returns customers belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    PosCustomer::factory(3)->create(['company_id' => $otherCompany->id]);
    PosCustomer::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can paginate customers with custom per_page', function () {
    PosCustomer::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('can search customers by name', function () {
    PosCustomer::factory()->create(['name' => 'Budi Santoso',  'company_id' => $this->company->id]);
    PosCustomer::factory()->create(['name' => 'Siti Aminah',   'company_id' => $this->company->id]);
    PosCustomer::factory()->create(['name' => 'Ahmad Fauzi',   'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers?search=budi');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Budi Santoso');
});

it('can search customers by phone', function () {
    PosCustomer::factory()->create(['phone' => '081234567890', 'company_id' => $this->company->id]);
    PosCustomer::factory()->create(['phone' => '089876543210', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers?search=081234');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/pos/customers')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a customer', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', [
            'name'                 => 'Budi Santoso',
            'address'              => 'Jl. Sudirman No. 1',
            'phone'                => '08123456789',
            'customer_type_uuid'   => $this->customerType->uuid,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Budi Santoso');
});

it('can create a customer without optional fields', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', [
            'name'               => 'Budi Santoso',
            'customer_type_uuid' => $this->customerType->uuid,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.phone', null);
});

it('can create a customer with customer type', function () {
    $vipType = PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', [
            'name'               => 'Budi Santoso',
            'address'            => 'Jl. Sudirman No. 1',
            'phone'              => '08123456789',
            'customer_type_uuid' => $vipType->uuid,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.customer_type_id.name', $vipType->name);
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', ['name' => str_repeat('a', 256)])
        ->assertStatus(422);
});

it('returns 422 when customer_type_id does not exist', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/customers', [
            'name'               => 'Budi Santoso',
            'customer_type_uuid' => '00000000-0000-0000-0000-000000000000',
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/pos/customers', ['name' => 'Budi Santoso'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get customer detail', function () {
    $customer = PosCustomer::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $customer->uuid);
});

it('returns 404 when customer not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/customers/uuid-tidak-ada')
        ->assertStatus(404);
});

it('returns 404 when accessing customer from other company', function () {
    $otherCompany = Company::factory()->create();
    $customer     = PosCustomer::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a customer', function () {
    $customer = PosCustomer::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customers/{$customer->uuid}", ['name' => 'Updated Name'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name');
});

it('can partial update (PATCH) customer without sending all fields', function () {
    $customer = PosCustomer::factory()->create([
        'name'       => 'Original Name',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customers/{$customer->uuid}", [])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Original Name');
});

it('can update customer type', function () {
    $customer = PosCustomer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    $vipType = PosCustomerType::factory()->create([
        'type'       => 'VIP',
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customers/{$customer->uuid}", [
            'customer_type_uuid' => $vipType->uuid,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.customer_type_id.name', $vipType->name);
});

it('returns 404 when updating customer from other company', function () {
    $otherCompany = Company::factory()->create();
    $customer     = PosCustomer::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/customers/{$customer->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent customer', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/pos/customers/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a customer', function () {
    $customer = PosCustomer::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosCustomer::withTrashed()->find($customer->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting customer that has sales transactions', function () {
    $customer = PosCustomer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    PosSalesTransaction::factory()->create([
        'customer_id'  => $customer->id,
        'created_by'   => $this->user->id,
        'company_id'   => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting customer from other company', function () {
    $otherCompany = Company::factory()->create();
    $customer     = PosCustomer::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent customer', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/pos/customers/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $customer = PosCustomer::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/pos/customers/{$customer->uuid}")
        ->assertStatus(401);
});