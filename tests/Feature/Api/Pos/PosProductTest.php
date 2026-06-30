<?php

use App\Models\PosCategory;
use App\Models\Company;
use App\Models\PosProduct;
use App\Models\PosStockMutation;
use App\Models\PosUnit;
use App\Models\User;
use App\Models\PosSalesDetail;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
    $this->category = PosCategory::factory()->create();
    $this->unit = PosUnit::factory()->create();
});

// =============================
// INDEX
// =============================

it('can get product list', function () {
    PosProduct::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'name', 'code', 'leader_price', 'stock']
            ]
        ])
        ->assertJsonPath('success', true);
});

// =============================
// GENERATE CODE
// =============================

it('can generate unique product code with company code prefix', function () {
    $this->company->update(['code' => 'ABC']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products/generate-code')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'ABC0001');
});

it('generates sequential product codes', function () {
    $this->company->update(['code' => 'XYZ']);

    PosProduct::factory()->create([
        'code' => 'XYZ0001',
        'company_id' => $this->company->id,
    ]);
    PosProduct::factory()->create([
        'code' => 'XYZ0002',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products/generate-code')
        ->assertStatus(200)
        ->assertJsonPath('data.code', 'XYZ0003');
});

it('returns 401 when not authenticated on generate code', function () {
    $this->getJson('/api/v1/pos/products/generate-code')->assertStatus(401);
});

it('only returns products belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    PosProduct::factory(3)->create(['company_id' => $otherCompany->id]);
    PosProduct::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products');

    expect($response->json('data'))->toHaveCount(2);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/pos/products')->assertStatus(401);
});

it('can filter products by search (name or code)', function () {
    PosProduct::factory()->create(['name' => 'Laptop Gaming', 'code' => 'LAP-001', 'company_id' => $this->company->id]);
    PosProduct::factory()->create(['name' => 'Mouse Wireless', 'code' => 'MOU-001', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products?search=laptop');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Laptop Gaming');
});

it('can sort products by name', function () {
    PosProduct::factory()->create(['name' => 'Zebra', 'company_id' => $this->company->id]);
    PosProduct::factory()->create(['name' => 'Apple', 'company_id' => $this->company->id]);
    PosProduct::factory()->create(['name' => 'Banana', 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products?order_by_key=name&order_by_value=asc');

    expect($response->json('data.0.name'))->toBe('Apple');
    expect($response->json('data.1.name'))->toBe('Banana');
    expect($response->json('data.2.name'))->toBe('Zebra');
});

it('can paginate products with custom per_page', function () {
    PosProduct::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products?per_page=5');

    expect($response->json('data'))->toHaveCount(5);
});

// =============================
// STORE
// =============================

it('can create a product', function () {
    $category = PosCategory::factory()->create(['company_id' => $this->company->id]);
    $unit = PosUnit::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name' => 'Product Test',
            'code' => 'PRD-001',
            'base_price' => 50000,
            'leader_price' => 75000,
            'stock' => 10,
            'min_stock' => 5,
            'category_uuid' => $category->uuid, 
            'unit_uuid' => $unit->uuid,         
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Product Test')
        ->assertJsonPath('data.code', 'PRD-001');
});

it('returns 422 when category_uuid is invalid', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name' => 'Product Test',
            'leader_price' => 75000,
            'category_uuid' => 'invalid-uuid',
        ])
        ->assertStatus(422);
});

it('returns 422 when unit_uuid is invalid', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name' => 'Product Test',
            'leader_price' => 75000,
            'unit_uuid' => 'invalid-uuid',
        ])
        ->assertStatus(422);
});

it('returns 422 when name is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when name exceeds 255 characters', function () {
    $longName = str_repeat('a', 256);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', ['name' => $longName])
        ->assertStatus(422);
});

it('returns 422 when leader_price is empty on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', ['name' => 'Test', 'leader_price' => ''])
        ->assertStatus(422);
});

it('returns 422 when code is duplicate within same company', function () {
    PosProduct::factory()->create([
        'code' => 'DUP-001',
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name' => 'Another Product',
            'code' => 'DUP-001',
            'leader_price' => 10000,
        ])
        ->assertStatus(422);
});

it('allows same product code in different companies', function () {
    $otherCompany = Company::factory()->create();
    PosProduct::factory()->create([
        'code' => 'SAME-001',
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name' => 'Product',
            'code' => 'SAME-001',
            'leader_price' => 10000,
        ])
        ->assertStatus(201);
});

// tests/Feature/Api/ProductTest.php — tambahkan di bagian STORE

it('creates ADJUST_IN stock mutation when product created with stock > 0', function () {
    $payload = [
        'name'        => 'Produk Test',
        'leader_price' => 10000,
        'stock'       => 50,
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', $payload)
        ->assertStatus(201);

    $this->assertDatabaseHas('pos_stock_mutations', [
        'type'         => 'ADJUST_IN',
        'quantity'     => 50,
        'stock_before' => 0,
        'stock_after'  => 50,
        'company_id'   => $this->company->id,
    ]);
});

it('does not create stock mutation when product created with stock 0', function () {
    $payload = [
        'name'        => 'Produk Test',
        'leader_price' => 10000,
        'stock'       => 0,
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', $payload)
        ->assertStatus(201);

    $this->assertDatabaseCount('pos_stock_mutations', 1);
});

it('does not create stock mutation when stock field is not provided', function () {
    $payload = [
        'name'        => 'Produk Test',
        'leader_price' => 10000,
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', $payload)
        ->assertStatus(201);

    $this->assertDatabaseCount('pos_stock_mutations', 1);
});

it('stock mutation notes contains product name', function () {
    $payload = [
        'name'        => 'Sabun Mandi Special',
        'leader_price' => 10000,
        'stock'       => 25,
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', $payload)
        ->assertStatus(201);

    $mutation = PosStockMutation::first();
    expect($mutation->notes)->toContain('Mutasi awal produk');
});

it('created_by in stock mutation matches authenticated user', function () {
    $payload = [
        'name'        => 'Produk Test',
        'leader_price' => 10000,
        'stock'       => 10,
    ];

    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', $payload)
        ->assertStatus(201);

    $this->assertDatabaseHas('pos_stock_mutations', [
        'created_by' => $this->user->id,
        'company_id' => $this->company->id,
    ]);
});

it('can create product with marketing_price', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name'            => 'Produk Test',
            'leader_price'     => 15000,
            'marketing_price' => 12000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.marketing_price', 12000);
});

it('marketing_price defaults to 0 when not provided', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name'        => 'Produk Test',
            'leader_price' => 15000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.marketing_price', 0);
});

it('can update marketing_price', function () {
    $product = PosProduct::factory()->create([
        'marketing_price' => 10000,
        'company_id'      => $this->company->id,
        'created_by'      => $this->user->id,
        'category_id'     => $this->category->id,
        'unit_id'         => $this->unit->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/products/{$product->uuid}", [
            'marketing_price' => 13000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.marketing_price', 13000);
});

it('returns 422 when marketing_price is negative', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/pos/products', [
            'name'            => 'Produk Test',
            'leader_price'     => 15000,
            'marketing_price' => -1000,
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/pos/products', ['name' => 'Test Product'])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get product detail', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.uuid', $product->uuid);
});

it('returns 404 when product not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/products/invalid-uuid')
        ->assertStatus(404);
});

it('returns 404 when accessing product from other company', function () {
    $otherCompany = Company::factory()->create();
    $product = PosProduct::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update a product', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/products/{$product->uuid}", [
            'name' => 'Updated Product',
            'leader_price' => 100000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Product')
        ->assertJsonPath('data.leader_price', 100000);
});

it('can partial update product without sending all fields', function () {
    $product = PosProduct::factory()->create([
        'name' => 'Original Name',
        'leader_price' => 50000,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/products/{$product->uuid}", ['name' => 'Only Name Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Only Name Updated')
        ->assertJsonPath('data.leader_price', 50000);
});

it('returns 404 when updating product from other company', function () {
    $otherCompany = Company::factory()->create();
    $product = PosProduct::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/pos/products/{$product->uuid}", ['name' => 'Hacked'])
        ->assertStatus(404);
});

it('returns 404 when updating non-existent product', function () {
    $this->actingAs($this->user)
        ->patchJson('/api/v1/pos/products/invalid-uuid', ['name' => 'New Name'])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a product', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosProduct::withTrashed()->find($product->id)->deleted_at)->not->toBeNull();
});

it('returns 422 when deleting product that has sales details', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    // Buat sales detail tanpa harus membuat sales transaction terpisah
    PosSalesDetail::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when deleting product from other company', function () {
    $otherCompany = Company::factory()->create();
    $product = PosProduct::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(404);
});

it('returns 404 when deleting non-existent product', function () {
    $this->actingAs($this->user)
        ->deleteJson('/api/v1/pos/products/invalid-uuid')
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/pos/products/{$product->uuid}")
        ->assertStatus(401);
});