<?php

use App\Models\Company;
use App\Models\User;
use App\Models\PosProduct;
use App\Models\PosSalesTransaction;
use App\Enums\Role;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->owner()->create([
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
                '*' => ['uuid', 'name', 'phone', 'email', 'role'] // ← username → phone
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

it('can filter marketings by search (name, phone, email)', function () { // ← username → phone
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

    // Search by phone number
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
// STORE
// =============================

it('can create a marketing', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', [
            'name'    => 'Ahmad Fauzi',
            'email'   => 'ahmad@example.com',
            'address' => 'Jl. Merdeka No. 10',
            'phone'   => '08123456789',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Ahmad Fauzi')
        ->assertJsonPath('data.role', Role::MARKETING->value);

    // Check credentials returned
    $response->assertJsonStructure([
        'success',
        'message',
        'data',
        'credentials' => ['phone', 'password'] // ← username → phone
    ]);

    // Check phone is returned as username (or keep username if auto-generated)
    expect($response->json('credentials.phone'))->toBe('08123456789');
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', ['name' => '', 'email' => 'test@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters on store', function () {
    $longName = str_repeat('a', 256);

    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', ['name' => $longName, 'email' => 'test@example.com'])
        ->assertStatus(422);
});

it('returns 422 when email is invalid', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', [
            'name'  => 'Test User',
            'email' => 'invalid-email'
        ])
        ->assertStatus(422);
});

it('returns 422 when email already exists', function () {
    User::factory()->marketing()->create([
        'email'      => 'existing@example.com',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', [
            'name'  => 'New User',
            'email' => 'existing@example.com'
        ])
        ->assertStatus(422);
});

it('allows same email in different companies', function () {
    $otherCompany = Company::factory()->create();
    User::factory()->marketing()->create([
        'email'      => 'same@example.com',
        'address'    => 'Jl. Merdeka No. 10',
        'phone'      => '08123456789',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/marketings', [
            'name'  => 'New User',
            'email' => 'same@example.com',
            'phone' => '08123456788', 
        ])
        ->assertStatus(201);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/marketings', ['name' => 'Test Marketing'])
        ->assertStatus(401);
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
// UPDATE
// =============================

it('can update a marketing', function () {
    $marketing = User::factory()->marketing()->create([
        'name'       => 'Original Name',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/marketings/{$marketing->uuid}", [
            'name'    => 'Updated Name',
            'address' => 'New Address'
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.address', 'New Address');
});

it('can partial update marketing without sending all fields', function () {
    $marketing = User::factory()->marketing()->create([
        'name'    => 'Original Name',
        'address' => 'Original Address',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/marketings/{$marketing->uuid}", ['name' => 'Only Name Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Only Name Updated')
        ->assertJsonPath('data.address', 'Original Address');
});

it('can update marketing password', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'password'   => bcrypt('oldpassword'),
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/marketings/{$marketing->uuid}", [
            'password' => 'newpassword123'
        ])
        ->assertStatus(200);

    $marketing->refresh();
    expect(Hash::check('newpassword123', $marketing->password))->toBeTrue();
});

it('returns 404 when updating marketing from other company', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/marketings/{$marketing->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent marketing', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/marketings/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a marketing', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(User::withTrashed()->find($marketing->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting marketing that has products', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $superAdmin = User::factory()->superAdmin()->create([
        'company_id' => $this->company->id,
    ]);

    $product = PosProduct::factory()->create([
        'company_id' => $this->company->id,
    ]);
    
    $marketing->marketingProducts()->create([
        'product_id'      => $product->id,
        'created_by'      => $superAdmin->id,
        'company_id'      => $this->company->id,
        'marketing_price' => 100000,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(422);
});

it('returns 422 when deleting marketing that has transactions', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    PosSalesTransaction::factory()->create([
        'created_by' => $marketing->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting marketing from other company', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent marketing', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/marketings/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->deleteJson("/api/v1/marketings/{$marketing->uuid}")
        ->assertStatus(401);
});