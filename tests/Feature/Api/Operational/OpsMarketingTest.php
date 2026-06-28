<?php

use App\Models\Company;
use App\Models\User;
use App\Models\PosProduct;
use App\Models\PosSalesTransaction;
use App\Enums\Role;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user    = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get marketing list via operational', function () {
    User::factory(5)->marketing()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings')
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

it('only returns marketings belonging to the same company via operational', function () {
    $otherCompany = Company::factory()->create();
    User::factory(3)->marketing()->create(['company_id' => $otherCompany->id]);
    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings');
    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('does not return non-marketing users via operational', function () {
    User::factory()->superAdmin()->create(['company_id' => $this->company->id]);
    User::factory()->owner()->create(['company_id' => $this->company->id]);
    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings');

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.role'))->toBe(Role::MARKETING->value);
});

it('returns 401 when not authenticated on operational index', function () {
    $this->getJson('/api/v1/operational/marketings')->assertStatus(401);
});

it('can filter marketings by search via operational', function () {
    User::factory()->marketing()->create([
        'name'       => 'Budi Santoso',
        'phone'      => '081234567890',
        'email'      => 'budi@example.com',
        'company_id' => $this->company->id,
    ]);
    User::factory()->marketing()->create([
        'name'       => 'Siti Aminah',
        'phone'      => '081234567891',
        'email'      => 'siti@example.com',
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings?search=budi');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Budi Santoso');
});

it('can sort marketings by name via operational', function () {
    User::factory()->marketing()->create(['name' => 'Zulkarnain', 'company_id' => $this->company->id]);
    User::factory()->marketing()->create(['name' => 'Agus', 'company_id' => $this->company->id]);
    User::factory()->marketing()->create(['name' => 'Budi', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings?order_by_key=name&order_by_value=asc');

    expect($response->json('data.0.name'))->toBe('Agus');
    expect($response->json('data.1.name'))->toBe('Budi');
    expect($response->json('data.2.name'))->toBe('Zulkarnain');
});

it('can paginate marketings with custom per_page via operational', function () {
    User::factory(20)->marketing()->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

// =============================
// SHOW
// =============================

it('can get marketing detail via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'name'       => 'Detail Marketing',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Detail Marketing')
        ->assertJsonPath('data.role', Role::MARKETING->value);
});

it('returns 404 when marketing not found on operational show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/operational/marketings/invalid-uuid')
        ->assertStatus(404);
});

it('returns 404 when accessing marketing from other company via operational', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(404);
});

// =============================
// STORE
// =============================

it('can create a marketing via operational', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', [
            'name'    => 'Ahmad Fauzi',
            'email'   => 'ahmad@example.com',
            'address' => 'Jl. Merdeka No. 10',
            'phone'   => '08123456789',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Ahmad Fauzi')
        ->assertJsonPath('data.role', Role::MARKETING->value);

    $response->assertJsonStructure([
        'success',
        'message',
        'data',
        'credentials' => ['phone', 'password']
    ]);

    expect($response->json('credentials.phone'))->toBe('08123456789');
});

it('returns 422 when name is empty on operational store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', ['name' => '', 'email' => 'test@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters on operational store', function () {
    $longName = str_repeat('a', 256);

    $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', ['name' => $longName, 'email' => 'test@example.com'])
        ->assertStatus(422);
});

it('returns 422 when email is invalid on operational store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', [
            'name'  => 'Test User',
            'email' => 'invalid-email'
        ])
        ->assertStatus(422);
});

it('returns 422 when email already exists on operational store', function () {
    User::factory()->marketing()->create([
        'email'      => 'existing@example.com',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', [
            'name'  => 'New User',
            'email' => 'existing@example.com'
        ])
        ->assertStatus(422);
});

it('allows same email in different companies on operational store', function () {
    $otherCompany = Company::factory()->create();
    User::factory()->marketing()->create([
        'email'      => 'same@example.com',
        'address'    => 'Jl. Merdeka No. 10',
        'phone'      => '08123456789',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/operational/marketings', [
            'name'  => 'New User',
            'email' => 'same@example.com',
            'phone' => '08123456788',
        ])
        ->assertStatus(201);
});

it('returns 401 when not authenticated on operational store', function () {
    $this->postJson('/api/v1/operational/marketings', ['name' => 'Test Marketing'])
        ->assertStatus(401);
});

// =============================
// UPDATE
// =============================

it('can update a marketing via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'name'       => 'Original Name',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/operational/marketings/{$marketing->uuid}", [
            'name'    => 'Updated Name',
            'address' => 'New Address'
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.address', 'New Address');
});

it('can partial update marketing without sending all fields via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'name'    => 'Original Name',
        'address' => 'Original Address',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/operational/marketings/{$marketing->uuid}", ['name' => 'Only Name Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Only Name Updated')
        ->assertJsonPath('data.address', 'Original Address');
});

it('can update marketing password via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
        'password'   => bcrypt('oldpassword'),
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/operational/marketings/{$marketing->uuid}", [
            'password' => 'newpassword123'
        ])
        ->assertStatus(200);

    $marketing->refresh();
    expect(Hash::check('newpassword123', $marketing->password))->toBeTrue();
});

it('returns 404 when updating marketing from other company via operational', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/operational/marketings/{$marketing->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent marketing via operational', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/operational/marketings/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a marketing via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(User::withTrashed()->find($marketing->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting marketing that has products via operational', function () {
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
        ->deleteJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(422);
});

it('returns 422 when deleting marketing that has transactions via operational', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    PosSalesTransaction::factory()->create([
        'created_by' => $marketing->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting marketing from other company via operational', function () {
    $otherCompany = Company::factory()->create();
    $marketing = User::factory()->marketing()->create([
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent marketing via operational', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/operational/marketings/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on operational delete', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $this->deleteJson("/api/v1/operational/marketings/{$marketing->uuid}")
        ->assertStatus(401);
});
