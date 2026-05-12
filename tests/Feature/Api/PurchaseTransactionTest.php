<?php

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Category;

beforeEach(function () {
    $this->company  = Company::factory()->create();
    $this->user     = User::factory()->owner()->create([
        'company_id' => $this->company->id,
    ]);
    $this->supplier = Supplier::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->category = Category::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->unit = Unit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->product = Product::factory()->create([
        'stock'       => 10,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);
    $this->product2 = Product::factory()->create([
        'stock'       => 5,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    // Payload default
    $this->payload = [
        'supplier_uuid'    => $this->supplier->uuid,
        'transaction_date' => '2026-05-03',
        'discount'         => 0,
        'total'            => 50000, // ← tambah
        'paid'             => 50000, // ← tambah
        'payment_type'     => PaymentType::CASH->value,
        'items'            => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
        ],
    ];
});


// =============================
// INDEX
// =============================

it('can get purchase transaction list', function () {
    PurchaseTransaction::factory(3)->create([
        'supplier_id' => $this->supplier->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['ulid', 'transaction_code', 'transaction_date', 'total']
            ]
        ]);
});

it('only returns transactions belonging to the same company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherSupplier = Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    PurchaseTransaction::factory(2)->create([
        'supplier_id' => $otherSupplier->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);
    PurchaseTransaction::factory(3)->create([
        'supplier_id' => $this->supplier->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

it('can filter by date range', function () {
    PurchaseTransaction::factory()->create([
        'transaction_date' => '2026-01-01',
        'supplier_id'      => $this->supplier->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);
    PurchaseTransaction::factory()->create([
        'transaction_date' => '2026-06-01',
        'supplier_id'      => $this->supplier->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?date_from=2026-05-01&date_to=2026-05-31');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/purchase-transactions')->assertStatus(401);
});

it('can search by transaction_code', function () {
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-ABC12345-20260505',
        'supplier_id' => $this->supplier->id,
        'company_id'  => $this->company->id,
    ]);
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-XYZ98765-20260505',
        'supplier_id' => $this->supplier->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?search=ABC');

    expect($response->json('data'))->toHaveCount(1);
});

it('can sort by different columns', function () {
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-001',
        'supplier_id' => $this->supplier->id,
        'company_id'  => $this->company->id,
    ]);
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-002',
        'supplier_id' => $this->supplier->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?order_by_key=transaction_code&order_by_value=ASC');

    $firstCode = $response->json('data.0.transaction_code');
    $secondCode = $response->json('data.1.transaction_code');
    expect($firstCode)->toBeLessThan($secondCode);
});

it('respects pagination per_page parameter', function () {
    PurchaseTransaction::factory(20)->create([
        'supplier_id' => $this->supplier->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?per_page=5');

    expect($response->json('data'))->toHaveCount(5);
});

it('can search purchase transactions by transaction code', function () {
    $supplier = Supplier::factory()->create(['company_id' => $this->company->id]);
    
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-ABC-123',
        'supplier_id' => $supplier->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-XYZ-789',
        'supplier_id' => $supplier->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?search=ABC');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.transaction_code'))->toBe('PO-ABC-123');
});

it('can search purchase transactions by supplier name', function () {
    $supplier1 = Supplier::factory()->create([
        'name' => 'PT Sumber Makmur',
        'company_id' => $this->company->id,
    ]);
    $supplier2 = Supplier::factory()->create([
        'name' => 'CV Maju Jaya',
        'company_id' => $this->company->id,
    ]);
    
    PurchaseTransaction::factory()->create([
        'supplier_id' => $supplier1->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    PurchaseTransaction::factory()->create([
        'supplier_id' => $supplier2->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?search=Sumber');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('can search purchase transactions by supplier name with case insensitive', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'PT SUMBER MAKMUR',
        'company_id' => $this->company->id,
    ]);
    
    PurchaseTransaction::factory()->create([
        'supplier_id' => $supplier->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?search=sumber');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('can search purchase transactions by transaction code and supplier name together', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'PT Sumber Makmur',
        'company_id' => $this->company->id,
    ]);
    
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-ABC-123',
        'supplier_id' => $supplier->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    PurchaseTransaction::factory()->create([
        'transaction_code' => 'PO-DEF-456',
        'supplier_id' => $supplier->id,
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions?search=ABC');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.transaction_code'))->toBe('PO-ABC-123');
});

// =============================
// STORE
// =============================

it('can create a purchase transaction', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'ulid',
                'transaction_code',
                'total',
                'payment_type',
                'transaction_status',
                'supplier',
                'items',
            ]
        ]);

    // Status harus PAID
    expect($response->json('data.transaction_status'))->toBe(TransactionStatus::PAID->value);
});

it('stock increases after purchase transaction', function () {
    $stockBefore = $this->product->stock;

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $this->product->refresh();
    expect($this->product->stock)->toBe($stockBefore + 5);
});

it('last_purchase_price updates after purchase', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $this->product->refresh();
    expect(floatval($this->product->last_purchase_price))->toBe(10000);
});

it('stock_mutation is created after purchase', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product->id,
        'type'       => 'PURCHASE_IN',
        'quantity'   => 5,
        'company_id' => $this->company->id,
    ]);
});

it('total is calculated correctly from items minus discount', function () {
    $payload = array_merge($this->payload, [
        'discount' => 5000,
        'total'    => 75000,
        'paid'     => 75000,
        'items'    => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 2,
                'buy_price'    => 10000,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 3,
                'buy_price'    => 20000,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload);

    // (2 * 10000) + (3 * 20000) - 5000 = 75000
    expect($response->json('data.total'))->toEqual(75000.0);
});

it('can create transaction with multiple items', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 3,
                'buy_price'    => 20000,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload);

    $response->assertStatus(201);
    expect($response->json('data.items'))->toHaveCount(2);
});

it('returns 422 when supplier_uuid not found', function () {
    $payload = array_merge($this->payload, ['supplier_uuid' => 'invalid-uuid']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when items is empty', function () {
    $payload = array_merge($this->payload, ['items' => []]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when product_uuid in items not found', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => 'invalid-uuid',
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when payment_type is invalid', function () {
    $payload = array_merge($this->payload, ['payment_type' => 'INVALID']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when quantity is zero', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 0,
                'buy_price'    => 10000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when total is missing', function () {
    $payload = $this->payload;
    unset($payload['total']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['total']]);
});

it('returns 422 when paid is missing', function () {
    $payload = $this->payload;
    unset($payload['paid']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['paid']]);
});

it('returns 422 when discount is greater than total', function () {
    $payload = array_merge($this->payload, [
        'total'    => 50000,
        'paid'     => 50000,
        'discount' => 60000, // ← lebih besar dari total
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['discount']]);
});

it('returns 422 when paid is lower than total', function () {
    $payload = array_merge($this->payload, [
        'total' => 50000,
        'paid'  => 30000, // ← lebih besar dari total
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['paid']]);
});

it('returns 422 when total is negative', function () {
    $payload = array_merge($this->payload, ['total' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when paid is negative', function () {
    $payload = array_merge($this->payload, ['paid' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when discount is negative', function () {
    $payload = array_merge($this->payload, ['discount' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when transaction_date is missing', function () {
    $payload = $this->payload;
    unset($payload['transaction_date']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['transaction_date']]);
});

it('returns 422 when transaction_date format is invalid', function () {
    $payload = array_merge($this->payload, ['transaction_date' => 'bukan-tanggal']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when buy_price is negative', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 5,
                'buy_price'    => -1000, // ← negatif
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when supplier belongs to other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherSupplier = Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $payload = array_merge($this->payload, [
        'supplier_uuid' => $otherSupplier->uuid,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when product belongs to other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherProduct = Product::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $otherProduct->uuid,
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('can create transaction with discount', function () {
    $payload = array_merge($this->payload, [
        'discount' => 5000,
        'total'    => 45000,
        'paid'     => 45000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload);

    $response->assertStatus(201);
    expect($response->json('data.discount'))->toEqual(5000);
    expect($response->json('data.total'))->toEqual(45000);
});

it('transaction_code is auto generated with PO prefix', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    expect($response->json('data.transaction_code'))->toStartWith('PO-');
});

it('each item creates a stock mutation', function () {
    $payload = array_merge($this->payload, [
        'total' => 110000,
        'paid'  => 110000,
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 3,
                'buy_price'    => 20000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload);

    $this->assertDatabaseCount('stock_mutations', 2);
});

it('rolls back when error occurs during store', function () {
    // Simulasi error dengan product dari company lain
    $otherCompany  = Company::factory()->create();
    $otherUser     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherProduct  = Product::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $otherProduct->uuid, // bukan milik company ini
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload)
        ->assertStatus(422);

    // Tidak ada transaksi yang tersimpan
    $this->assertDatabaseCount('purchase_transactions', 0);
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/purchase-transactions', $this->payload)
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get purchase transaction detail', function () {
    $transaction = PurchaseTransaction::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/purchase-transactions/{$transaction->ulid}")
        ->assertStatus(200)
        ->assertJsonPath('data.ulid', (string) $transaction->ulid);
});

it('returns 404 when transaction not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/purchase-transactions/invalid-ulid')
        ->assertStatus(404);
});

it('returns 404 when accessing transaction from other company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherSupplier = Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $transaction = PurchaseTransaction::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/purchase-transactions/{$transaction->ulid}")
        ->assertStatus(404);
});

// =============================
// CANCEL
// =============================

it('can cancel a purchase transaction', function () {
    // Buat via store agar stok ter-update
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $stockAfterPurchase = $this->product->fresh()->stock;

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel")
        ->assertStatus(200)
        ->assertJsonPath('data.transaction_status', TransactionStatus::CANCEL->value);

    // Stok harus kembali berkurang
    expect($this->product->fresh()->stock)->toBe($stockAfterPurchase - 5);
});

it('stock_mutation ADJUST_OUT is created after cancel', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel");

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product->id,
        'type'       => 'ADJUST_OUT',
        'company_id' => $this->company->id,
    ]);
});

it('returns 422 when cancelling already cancelled transaction', function () {
    $transaction = PurchaseTransaction::factory()->create([
        'transaction_status' => TransactionStatus::CANCEL,
        'supplier_id'        => $this->supplier->id,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when cancelling transaction from other company', function () {
    $otherCompany  = Company::factory()->create();
    $otherUser     = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherSupplier = Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);

    $transaction = PurchaseTransaction::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(404);
});

it('returns 401 when not authenticated on cancel', function () {
    $transaction = PurchaseTransaction::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->patchJson("/api/v1/purchase-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(401);
});

it('cancelled transaction cannot be cancelled again', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    // Cancel pertama
    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel");

    // Cancel kedua — harus 422
    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('stock returns to original after cancel', function () {
    $stockOriginal = $this->product->stock;

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel");

    expect($this->product->fresh()->stock)->toBe($stockOriginal);
});

it('cancel creates ADJUST_OUT stock mutation for each item', function () {
    $payload = array_merge($this->payload, [
        'total' => 110000,
        'paid'  => 110000,
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 5,
                'buy_price'    => 10000,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 3,
                'buy_price'    => 20000,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/purchase-transactions', $payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$ulid}/cancel");

    // 2 PURCHASE_IN + 2 ADJUST_OUT
    $this->assertDatabaseCount('stock_mutations', 4);

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product->id,
        'type'       => 'ADJUST_OUT',
    ]);

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product2->id,
        'type'       => 'ADJUST_OUT',
    ]);
});

it('returns 422 when cancelling non-PAID transaction', function () {
    $transaction = PurchaseTransaction::factory()->create([
        'transaction_status' => TransactionStatus::PENDING,
        'supplier_id'        => $this->supplier->id,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/purchase-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});