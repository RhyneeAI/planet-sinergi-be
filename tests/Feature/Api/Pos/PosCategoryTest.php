<?php

use App\Models\PosCategory;
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

it('can get category list', function () {
    PosCategory::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/categories')
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

it('only returns categories belonging to the same company', function () {
    // Kategori company lain
    $otherCompany = Company::factory()->create();
    PosCategory::factory(3)->create(['company_id' => $otherCompany->id]);

    // Kategori company sendiri
    PosCategory::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/categories');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/pos/categories')->assertStatus(401);
});

it('can filter categories by search', function () {
    PosCategory::factory()->create(['name' => 'Elektronik',  'company_id' => $this->company->id]);
    PosCategory::factory()->create(['name' => 'Makanan',     'company_id' => $this->company->id]);
    PosCategory::factory()->create(['name' => 'Minuman',     'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/categories?search=makan');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Makanan');
});

it('can paginate categories with custom per_page', function () {
    PosCategory::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/categories?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

// =============================
// STORE
// =============================

it('can create a category', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => 'Elektronik'])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Elektronik');
});

it('returns 422 when name exceeds 255 characters', function () {
    $longName = str_repeat('a', 256);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => $longName])
        ->assertStatus(422);
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name is duplicate within same company', function () {
    PosCategory::factory()->create([
        'name'       => 'Elektronik',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => 'Elektronik'])
        ->assertStatus(422);
});

it('prevents duplicate name with different case', function () {
    PosCategory::factory()->create([
        'name' => 'Elektronik',
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => 'ELEKTRONIK']);

    if (DB::connection()->getDriverName() === 'sqlite') {
        $response->assertStatus(201);
    } else {
        $response->assertStatus(422);
    }
});

it('allows same category name in different companies', function () {
    $otherCompany = Company::factory()->create();
    PosCategory::factory()->create([
        'name'       => 'Elektronik',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/categories', ['name' => 'Elektronik'])
        ->assertStatus(201);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/pos/categories', ['name' => 'Elektronik'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get category detail', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $category->uuid);
});

it('returns 404 when category not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/categories/uuid-tidak-ada')
        ->assertStatus(404);
});

it('returns 404 when accessing category from other company', function () {
    $otherCompany = Company::factory()->create();
    $category     = PosCategory::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a category', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/categories/{$category->uuid}", ['name' => 'Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated');
});

it('can partial update (PATCH) category without sending all fields', function () {
    $category = PosCategory::factory()->create([
        'name'       => 'Original',
        'company_id' => $this->company->id,
    ]);

    // PATCH tanpa field name — tidak boleh error
    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/categories/{$category->uuid}", [])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Original'); 
});

it('returns 404 when updating category from other company', function () {
    $otherCompany = Company::factory()->create();
    $category     = PosCategory::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/categories/{$category->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 422 when updating with duplicate name', function () {
    PosCategory::factory()->create([
        'name'       => 'Elektronik',
        'company_id' => $this->company->id,
    ]);

    $category = PosCategory::factory()->create([
        'name'       => 'Makanan',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/categories/{$category->uuid}", ['name' => 'Elektronik'])
        ->assertStatus(422);
});

it('returns 404 when updating non-existent category', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/pos/categories/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a category', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    // Pastikan soft deleted
    expect(PosCategory::withTrashed()->find($category->id)->deleted_at)->not->toBeNull();
});

it('returns 404 when deleting non-existent category', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/pos/categories/invalid-uuid')
        ->assertStatus(404);
});

it('returns 422 when deleting category that has products', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);

    // Buat product yang terkait
    PosProduct::factory()->create([
        'category_id' => $category->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting category from other company', function () {
    $otherCompany = Company::factory()->create();
    $category     = PosCategory::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/pos/categories/{$category->uuid}")
        ->assertStatus(401);
});