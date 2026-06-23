<?php

use App\Enums\PosStockMutationType;
use App\Models\Company;
use App\Models\PosProduct;
use App\Models\PosStockMutation;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX (List Products with mutations)
// =============================

it('can get product list that has stock mutations', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    PosStockMutation::factory(5)->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/products') // ← route baru
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['uuid', 'name', 'code', 'current_stock']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns products belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    $product1 = PosProduct::factory()->create(['company_id' => $otherCompany->id]);
    $product2 = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    PosStockMutation::factory(3)->create(['product_id' => $product1->id, 'company_id' => $otherCompany->id]);
    PosStockMutation::factory(2)->create(['product_id' => $product2->id, 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/products'); // ← route baru

    expect($response->json('data'))->toHaveCount(1);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/stock-mutations/products')->assertStatus(401); // ← route baru
});

it('can filter stock mutations by date range', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'created_at' => '2026-01-15',
        'company_id' => $this->company->id,
    ]);
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'created_at' => '2026-02-15',
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/products?date_from=2026-02-01&date_to=2026-02-28'); // ← route baru

    // Tetap return product karena ada mutasi dalam range tanggal
    expect($response->json('data'))->toHaveCount(1);
});

it('can sort products by name', function () {
    $product1 = PosProduct::factory()->create(['name' => 'Zebra', 'company_id' => $this->company->id]);
    $product2 = PosProduct::factory()->create(['name' => 'Apple', 'company_id' => $this->company->id]);
    
    PosStockMutation::factory()->create(['product_id' => $product1->id, 'company_id' => $this->company->id]);
    PosStockMutation::factory()->create(['product_id' => $product2->id, 'company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/products?order_by_key=product_name&order_by_value=asc'); // ← route baru

    expect($response->json('data.0.name'))->toBe('Apple');
    expect($response->json('data.1.name'))->toBe('Zebra');
});

// =============================
// SHOW (Mutations per Product)
// =============================

it('can get stock mutations for a specific product', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    PosStockMutation::factory(5)->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}") 
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'product' => ['uuid', 'name', 'code', 'current_stock'],
                'mutations' => [
                    'data' => [
                        '*' => ['ulid', 'type_label', 'quantity', 'stock_before', 'stock_after']
                    ]
                ]
            ]
        ])
        ->assertJsonPath('success', true);
});

it('can filter stock mutations by date range (show)', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    // Mutasi di tanggal 15 Januari
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'created_at' => '2026-01-15 10:00:00',
    ]);
    
    // Mutasi di tanggal 20 Januari
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'created_at' => '2026-01-20 14:00:00',
    ]);
    
    // Mutasi di tanggal 25 Januari
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'created_at' => '2026-01-25 09:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}?date_from=2026-01-16&date_to=2026-01-22");

    $response->assertStatus(200);
    expect($response->json('data.mutations.data'))->toHaveCount(1);
});

it('can filter stock mutations by type (show)', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::PURCHASE_IN,
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::SALES_OUT,
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::ADJUST_IN,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}?type=" . PosStockMutationType::PURCHASE_IN->value);

    $response->assertStatus(200);
    expect($response->json('data.mutations.data'))->toHaveCount(1);
    expect($response->json('data.mutations.data.0.type'))->toBe(PosStockMutationType::PURCHASE_IN->value);
});

it('can search stock mutations by notes (show)', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'notes' => 'Pembelian awal dari supplier',
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'notes' => 'Penjualan ke customer',
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'notes' => 'Adjustment stok opname',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}?search=pembelian");

    $response->assertStatus(200);
    expect($response->json('data.mutations.data'))->toHaveCount(1);
    expect($response->json('data.mutations.data.0.notes'))->toContain('Pembelian');
});

it('can sort stock mutations by quantity (show)', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'quantity' => 10,
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'quantity' => 50,
    ]);
    
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'quantity' => 25,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}?order_by_key=quantity&order_by_value=asc");

    $response->assertStatus(200);
    $quantities = collect($response->json('data.mutations.data'))->pluck('quantity');
    expect($quantities[0])->toBe(10);
    expect($quantities[1])->toBe(25);
    expect($quantities[2])->toBe(50);
});

it('can combine multiple filters (date range, type, search) (show)', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);
    
    // Data yang cocok: PURCHASE_IN, notes mengandung "supplier", tanggal 20 Jan
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::PURCHASE_IN,
        'notes' => 'Pembelian dari supplier utama',
        'created_at' => '2026-01-20 10:00:00',
    ]);
    
    // Data yang tidak cocok: type SALES_OUT
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::SALES_OUT,
        'notes' => 'Pembelian dari supplier utama',
        'created_at' => '2026-01-20 10:00:00',
    ]);
    
    // Data yang tidak cocok: tanggal di luar range
    PosStockMutation::factory()->create([
        'product_id' => $product->id,
        'company_id' => $this->company->id,
        'type' => PosStockMutationType::PURCHASE_IN,
        'notes' => 'Pembelian dari supplier utama',
        'created_at' => '2026-01-30 10:00:00',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}?date_from=2026-01-15&date_to=2026-01-25&type=" . PosStockMutationType::PURCHASE_IN->value . "&search=supplier");

    $response->assertStatus(200);
    expect($response->json('data.mutations.data'))->toHaveCount(1);
});

it('returns 404 when product not found', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/products/invalid-uuid') // ← route baru
        ->assertStatus(404);
});

it('returns 404 when accessing product from other company', function () {
    $otherCompany = Company::factory()->create();
    $product = PosProduct::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/products/{$product->uuid}") // ← route baru
        ->assertStatus(404);
});

// =============================
// STORE (Create Adjustment/Opname)
// =============================

it('can create ADJUST_IN stock mutation', function () {
    $product = PosProduct::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::ADJUST_IN->value,
            'quantity' => 50,
            'product_uuid' => $product->uuid, // ← ganti product_id ke product_uuid
            'notes' => 'Adjustment tambah stok',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(150);
});

it('can create ADJUST_OUT stock mutation', function () {
    $product = PosProduct::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::ADJUST_OUT->value,
            'quantity' => 30,
            'product_uuid' => $product->uuid, // ← ganti product_id ke product_uuid
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(70);
});

it('can create OPNAME stock mutation', function () {
    $product = PosProduct::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::OPNAME->value,
            'quantity' => 120,
            'product_uuid' => $product->uuid, // ← ganti product_id ke product_uuid
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(120);
});

it('returns 422 when adjusting more stock than available', function () {
    $product = PosProduct::factory()->create([
        'stock' => 10,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::ADJUST_OUT->value,
            'quantity' => 100,
            'product_uuid' => $product->uuid, // ← ganti product_id ke product_uuid
        ])
        ->assertStatus(422);
});

it('returns 422 when product not found on store', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::ADJUST_IN->value,
            'quantity' => 50,
            'product_uuid' => 'invalid-uuid', // ← ganti
        ])
        ->assertStatus(422);
});

it('returns 422 when type is not allowed for manual creation', function () {
    $product = PosProduct::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => PosStockMutationType::PURCHASE_IN->value,
            'quantity' => 50,
            'product_uuid' => $product->uuid, // ← ganti
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/stock-mutations', [])
        ->assertStatus(401);
});