<?php

use App\Models\PosCategory;
use App\Models\Company;
use App\Models\PosMarketingProduct;
use App\Models\PosProduct;
use App\Models\PosUnit;
use App\Models\User;

beforeEach(function () {
    $this->company   = Company::factory()->create();
    $this->owner     = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->marketing = User::factory()->marketing()->create(['company_id' => $this->company->id]);
    $this->category  = PosCategory::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->unit      = PosUnit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->product   = PosProduct::factory()->create([
        'sales_price' => 15000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $this->product2  = PosProduct::factory()->create([
        'sales_price' => 20000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    $this->payload = [
        'product_uuid'    => $this->product->uuid,
        'marketing_uuid'  => $this->marketing->uuid,
        'marketing_price' => 12000,
    ];
});

// =============================
// INDEX
// =============================

it('can get marketing product list', function () {
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success', 'message',
            'data' => ['*' => ['uuid', 'marketing_price']]
        ]);
});

it('only returns marketing products belonging to the same company', function () {
    $otherCompany   = Company::factory()->create();
    $otherOwner     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherMarketing = User::factory()->marketing()->create(['company_id' => $otherCompany->id]);
    
    // Company lain punya 3 marketing product dengan produk berbeda
    $otherProducts = PosProduct::factory(3)->create([
        'company_id'  => $otherCompany->id,
        'created_by'  => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);
    
    foreach ($otherProducts as $otherProduct) {
        PosMarketingProduct::factory()->create([
            'product_id'   => $otherProduct->id,
            'marketing_id' => $otherMarketing->id,
            'created_by'   => $otherOwner->id,
            'company_id'   => $otherCompany->id,
        ]);
    }

    // Company sendiri punya 2
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product2->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 18000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can filter by marketing_uuid', function () {
    $marketing2 = User::factory()->marketing()->create(['company_id' => $this->company->id]);

    // Marketing 1 punya 2 produk
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product2->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 18000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    // Marketing 2 punya 1 produk
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $marketing2->id,
        'marketing_price' => 11000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/v1/marketing-products?marketing_uuid={$this->marketing->uuid}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('can search marketing products by product name', function () {
    // Buat produk dengan nama spesifik
    $productA = PosProduct::factory()->create([
        'name'        => 'Sabun Mandi',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $productB = PosProduct::factory()->create([
        'name'        => 'Shampoo Rambut',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    PosMarketingProduct::factory()->create([
        'product_id'   => $productA->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'   => $productB->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products?search=sabun');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.product.name'))->toBe('Sabun Mandi');
});

it('can search marketing products by product code', function () {
    $productA = PosProduct::factory()->create([
        'code'        => 'PRD-SABUN-001',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $productB = PosProduct::factory()->create([
        'code'        => 'PRD-SHAMPOO-001',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    PosMarketingProduct::factory()->create([
        'product_id'   => $productA->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'   => $productB->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products?search=PRD-SABUN');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('can sort by product name ascending', function () {
    $productA = PosProduct::factory()->create([
        'name'        => 'Zee Product',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $productB = PosProduct::factory()->create([
        'name'        => 'Aaa Product',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    PosMarketingProduct::factory()->create([
        'product_id'   => $productA->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'   => $productB->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products?order_by_key=product_name&order_by_value=ASC');

    $response->assertStatus(200);
    expect($response->json('data.0.product.name'))->toBe('Aaa Product');
    expect($response->json('data.1.product.name'))->toBe('Zee Product');
});

it('can sort by product name descending', function () {
    $productA = PosProduct::factory()->create([
        'name'        => 'Zee Product',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $productB = PosProduct::factory()->create([
        'name'        => 'Aaa Product',
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    PosMarketingProduct::factory()->create([
        'product_id'   => $productA->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);
    PosMarketingProduct::factory()->create([
        'product_id'   => $productB->id,
        'marketing_id' => $this->marketing->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products?order_by_key=product_name&order_by_value=DESC');

    $response->assertStatus(200);
    expect($response->json('data.0.product.name'))->toBe('Zee Product');
    expect($response->json('data.1.product.name'))->toBe('Aaa Product');
});

it('can paginate marketing products', function () {
    $products = PosProduct::factory(10)->create([
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    foreach ($products as $product) {
        PosMarketingProduct::factory()->create([
            'product_id'   => $product->id,
            'marketing_id' => $this->marketing->id,
            'created_by'   => $this->owner->id,
            'company_id'   => $this->company->id,
        ]);
    }

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products?per_page=5');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('returns empty data when no marketing products exist', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/marketing-products')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a marketing product', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $this->payload)
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.marketing_price', 12000);
});

it('returns 422 when product_uuid is missing', function () {
    $payload = $this->payload;
    unset($payload['product_uuid']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['product_uuid']]);
});

it('returns 422 when marketing_uuid is missing', function () {
    $payload = $this->payload;
    unset($payload['marketing_uuid']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing_price is missing', function () {
    $payload = $this->payload;
    unset($payload['marketing_price']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_price']]);
});

it('returns 422 when product already assigned to same marketing', function () {
    // Buat marketing product pertama
    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $this->payload)
        ->assertStatus(201);

    // Coba assign produk yang sama ke marketing yang sama
    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $this->payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('allows same product assigned to different marketing', function () {
    $marketing2 = User::factory()->marketing()->create(['company_id' => $this->company->id]);

    // Assign product ke marketing 1
    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $this->payload)
        ->assertStatus(201);

    // Assign product yang sama ke marketing 2 — boleh
    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', [
            'product_uuid'    => $this->product->uuid,
            'marketing_uuid'  => $marketing2->uuid,
            'marketing_price' => 11000,
        ])
        ->assertStatus(201);
});

it('returns 422 when marketing_uuid is not a marketing role', function () {
    // Coba pakai owner uuid sebagai marketing
    $payload = array_merge($this->payload, [
        'marketing_uuid' => $this->owner->uuid,
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $payload)
        ->assertStatus(422);
});

it('returns 422 when marketing_price is negative', function () {
    $payload = array_merge($this->payload, ['marketing_price' => -1000]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/marketing-products', $payload)
        ->assertStatus(422);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/marketing-products', $this->payload)
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get marketing product detail', function () {
    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('data.uuid', $mp->uuid);
});

it('returns 404 when marketing product not found', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/marketing-products/invalid-uuid')
        ->assertStatus(404);
});

it('returns 404 when accessing marketing product from other company', function () {
    $otherCompany   = Company::factory()->create();
    $otherOwner     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherMarketing = User::factory()->marketing()->create(['company_id' => $otherCompany->id]);
    $otherProduct   = PosProduct::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $otherProduct->id,
        'marketing_id'    => $otherMarketing->id,
        'marketing_price' => 12000,
        'created_by'      => $otherOwner->id,
        'company_id'      => $otherCompany->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(404);
});

// =============================
// UPDATE
// =============================

it('can update marketing price', function () {
    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/marketing-products/{$mp->uuid}", [
            'marketing_price' => 14000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.marketing_price', 14000);
});

it('can partial update without sending product_uuid or marketing_uuid', function () {
    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/marketing-products/{$mp->uuid}", [
            'marketing_price' => 13000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.marketing_price', 13000);
});

it('returns 404 when updating marketing product from other company', function () {
    $otherCompany   = Company::factory()->create();
    $otherOwner     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherMarketing = User::factory()->marketing()->create(['company_id' => $otherCompany->id]);
    $otherProduct   = PosProduct::factory()->create([
        'company_id'  => $otherCompany->id,
        'created_by'  => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $otherProduct->id,
        'marketing_id'    => $otherMarketing->id,
        'marketing_price' => 12000,
        'created_by'      => $otherOwner->id,
        'company_id'      => $otherCompany->id,
    ]);

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/marketing-products/{$mp->uuid}", ['marketing_price' => 99000])
        ->assertStatus(404);
});

// =============================
// DESTROY
// =============================

it('can delete a marketing product', function () {
    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->deleteJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(PosMarketingProduct::withTrashed()->find($mp->id)->deleted_at)->not->toBeNull();
});

it('returns 404 when deleting marketing product from other company', function () {
    $otherCompany   = Company::factory()->create();
    $otherOwner     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherMarketing = User::factory()->marketing()->create(['company_id' => $otherCompany->id]);
    $otherProduct   = PosProduct::factory()->create([
        'company_id'  => $otherCompany->id,
        'created_by'  => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $otherProduct->id,
        'marketing_id'    => $otherMarketing->id,
        'marketing_price' => 12000,
        'created_by'      => $otherOwner->id,
        'company_id'      => $otherCompany->id,
    ]);

    $this->actingAs($this->owner)
        ->deleteJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(404);
});

it('returns 401 when not authenticated on delete', function () {
    $mp = PosMarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->deleteJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(401);
});