<?php

use App\Enums\StockMutationType;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->owner()->create([
        'company_id' => $this->company->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get stock mutation list', function () {
    StockMutation::factory(5)->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['ulid', 'type', 'quantity', 'stock_before', 'stock_after', 'created_at']
            ]
        ])
        ->assertJsonPath('success', true);
});

it('only returns stock mutations belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    StockMutation::factory(3)->create(['company_id' => $otherCompany->id]);
    StockMutation::factory(2)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations');

    expect($response->json('data'))->toHaveCount(2);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/stock-mutations')->assertStatus(401);
});

it('can filter stock mutations by date range', function () {
    StockMutation::factory()->create([
        'created_at' => '2026-01-15',
        'company_id' => $this->company->id,
    ]);
    StockMutation::factory()->create([
        'created_at' => '2026-02-15',
        'company_id' => $this->company->id,
    ]);
    StockMutation::factory()->create([
        'created_at' => '2026-03-15',
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations?date_from=2026-02-01&date_to=2026-02-28');

    expect($response->json('data'))->toHaveCount(1);
});

it('can sort stock mutations', function () {
    StockMutation::factory()->create([
        'quantity' => 10,
        'company_id' => $this->company->id,
    ]);
    StockMutation::factory()->create([
        'quantity' => 50,
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations?order_by_key=quantity&order_by_value=desc');

    expect($response->json('data.0.quantity'))->toBe(50);
});

// =============================
// STORE
// =============================

it('can create ADJUST_IN stock mutation', function () {
    $product = Product::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::ADJUST_IN->value,
            'quantity' => 50,
            'product_id' => $product->id,
            'notes' => 'Adjustment tambah stok',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(150);
});

it('can create ADJUST_OUT stock mutation', function () {
    $product = Product::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::ADJUST_OUT->value,
            'quantity' => 30,
            'product_id' => $product->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(70);
});

it('can create OPNAME stock mutation', function () {
    $product = Product::factory()->create([
        'stock' => 100,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::OPNAME->value,
            'quantity' => 120,
            'product_id' => $product->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $product->refresh();
    expect($product->stock)->toBe(120);
});

it('returns 422 when adjusting more stock than available', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::ADJUST_OUT->value,
            'quantity' => 100,
            'product_id' => $product->id,
        ])
        ->assertStatus(422);
});

it('returns 422 when product not found', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::ADJUST_IN->value,
            'quantity' => 50,
            'product_id' => 99999,
        ])
        ->assertStatus(422);
});

it('returns 422 when type is not allowed', function () {
    $product = Product::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/stock-mutations', [
            'type' => StockMutationType::PURCHASE_IN->value,
            'quantity' => 50,
            'product_id' => $product->id,
        ])
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/stock-mutations', [])
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get stock mutation detail', function () {
    $stockMutation = StockMutation::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/{$stockMutation->ulid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ulid', (string) $stockMutation->ulid);
});

it('returns 404 when stock mutation not found', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/stock-mutations/invalid-ulid')
        ->assertStatus(404);
});

it('returns 404 when accessing from other company', function () {
    $otherCompany = Company::factory()->create();
    $stockMutation = StockMutation::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/stock-mutations/{$stockMutation->ulid}")
        ->assertStatus(404);
});