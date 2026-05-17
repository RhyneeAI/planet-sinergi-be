<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\MarketingProduct;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->company   = Company::factory()->create();
    $this->owner     = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->marketing = User::factory()->marketing()->create(['company_id' => $this->company->id]);
    $this->category  = Category::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->unit      = Unit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->product   = Product::factory()->create([
        'sales_price' => 15000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
    $this->product2  = Product::factory()->create([
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
    MarketingProduct::factory()->create([
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
    $mp = MarketingProduct::factory()->create([
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
    $otherProduct   = Product::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = MarketingProduct::factory()->create([
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
    $mp = MarketingProduct::factory()->create([
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
    $mp = MarketingProduct::factory()->create([
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
    $otherProduct   = Product::factory()->create([
        'company_id'  => $otherCompany->id,
        'created_by'  => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = MarketingProduct::factory()->create([
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
    $mp = MarketingProduct::factory()->create([
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

    expect(MarketingProduct::withTrashed()->find($mp->id)->deleted_at)->not->toBeNull();
});

it('returns 404 when deleting marketing product from other company', function () {
    $otherCompany   = Company::factory()->create();
    $otherOwner     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherMarketing = User::factory()->marketing()->create(['company_id' => $otherCompany->id]);
    $otherProduct   = Product::factory()->create([
        'company_id'  => $otherCompany->id,
        'created_by'  => $otherOwner->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    $mp = MarketingProduct::factory()->create([
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
    $mp = MarketingProduct::factory()->create([
        'product_id'      => $this->product->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 12000,
        'created_by'      => $this->owner->id,
        'company_id'      => $this->company->id,
    ]);

    $this->deleteJson("/api/v1/marketing-products/{$mp->uuid}")
        ->assertStatus(401);
});