<?php

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->user         = User::factory()->owner()->create([
        'company_id' => $this->company->id,
    ]);
    $this->customerType = CustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->customer     = Customer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);
    $this->category     = Category::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->unit         = Unit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->user->id,
    ]);
    $this->product      = Product::factory()->create([
        'stock'       => 20,
        'sales_price' => 15000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);
    $this->product2     = Product::factory()->create([
        'stock'       => 10,
        'sales_price' => 25000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->payload = [
        'customer_uuid'    => $this->customer->uuid,
        'transaction_date' => '2026-05-03',
        'discount'         => 0,
        'total'            => 45000,
        'paid'             => 45000,
        'payment_type'     => PaymentType::CASH->value,
        'items'            => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 3,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
        ],
    ];
});

// =============================
// INDEX
// =============================

it('can get sales transaction list', function () {
    SalesTransaction::factory(3)->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions')
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
    $otherCompany      = Company::factory()->create();
    $otherUser         = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherCustomerType = CustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);
    $otherCustomer     = Customer::factory()->create([
        'customer_type_id' => $otherCustomerType->id,
        'created_by'       => $otherUser->id,
        'company_id'       => $otherCompany->id,
    ]);

    SalesTransaction::factory(2)->create([
        'customer_id' => $otherCustomer->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);
    SalesTransaction::factory(3)->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

it('can filter by date range', function () {
    SalesTransaction::factory()->create([
        'transaction_date' => '2026-01-01',
        'customer_id'      => $this->customer->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);
    SalesTransaction::factory()->create([
        'transaction_date' => '2026-06-01',
        'customer_id'      => $this->customer->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions?date_from=2026-05-01&date_to=2026-05-31');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

// tests/Feature/Api/SalesTransactionTest.php — tambahkan di bagian INDEX

it('can filter by created_by_uuid', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    // Transaksi oleh owner
    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    // Transaksi oleh marketing
    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $marketing->id,
        'company_id'  => $this->company->id,
    ]);
    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $marketing->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/sales-transactions?created_by_uuid={$marketing->uuid}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns all transactions when created_by_uuid is not provided', function () {
    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);
    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $marketing->id,
        'company_id'  => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns empty when created_by_uuid has no transactions', function () {
    SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $marketing = User::factory()->marketing()->create([
        'company_id' => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/sales-transactions?created_by_uuid={$marketing->uuid}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

it('returns 401 when not authenticated on index', function () {
    $this->getJson('/api/v1/sales-transactions')->assertStatus(401);
});

// =============================
// STORE
// =============================

it('can create a sales transaction', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'ulid',
                'transaction_code',
                'total',
                'payment_type',
                'transaction_status',
                'customer',
                'items',
            ]
        ]);

    expect($response->json('data.transaction_status'))->toBe(TransactionStatus::PAID->value);
});

it('can create sales transaction without customer', function () {
    $payload = $this->payload;
    unset($payload['customer_uuid']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload);

    $response->assertStatus(201);
    expect($response->json('data.customer'))->toBeNull();
});

it('stock decreases after sales transaction', function () {
    $stockBefore = $this->product->stock;

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $this->product->refresh();
    expect($this->product->stock)->toBe($stockBefore - 3);
});

it('stock_mutation SALES_OUT is created after sales', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product->id,
        'type'       => 'SALES_OUT',
        'quantity'   => 3,
        'company_id' => $this->company->id,
    ]);
});

it('returns 422 when stock is insufficient', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 999, // ← melebihi stok
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
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
                'quantity'     => 1,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when items is empty', function () {
    $payload = array_merge($this->payload, ['items' => []]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when payment_type is invalid', function () {
    $payload = array_merge($this->payload, ['payment_type' => 'INVALID']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when discount is greater than total', function () {
    $payload = array_merge($this->payload, [
        'discount' => 60000,
        'total'    => 45000,
        'paid'     => 45000,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['discount']]);
});

it('returns 422 when paid is lower than total', function () {
    $payload = array_merge($this->payload, [
        'total' => 45000,
        'paid'  => 30000,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['paid']]);
});

it('returns 422 when transaction_date is missing', function () {
    $payload = $this->payload;
    unset($payload['transaction_date']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['transaction_date']]);
});

it('transaction_code is auto generated with SO prefix', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    expect($response->json('data.transaction_code'))->toStartWith('SO-');
});

it('each item creates a SALES_OUT stock mutation', function () {
    $payload = array_merge($this->payload, [
        'total' => 120000,
        'paid'  => 120000,
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 3,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 2,
                'sell_price'   => 25000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload);

    $this->assertDatabaseCount('stock_mutations', 2);
});

// tests/Feature/Api/SalesTransactionTest.php — tambahkan

it('can create sales transaction with additional cost', function () {
    $payload = array_merge($this->payload, [
        'additional_cost'      => 15000,
        'additional_cost_note' => 'Ongkos kirim',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.additional_cost', 15000)
        ->assertJsonPath('data.additional_cost_note', 'Ongkos kirim');
});

it('additional_cost defaults to 0 when not provided', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.additional_cost', 0);
});

it('returns 422 when additional_cost is negative', function () {
    $payload = array_merge($this->payload, ['additional_cost' => -5000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('can create transaction with additional_cost_note only without amount', function () {
    $payload = array_merge($this->payload, [
        'additional_cost_note' => 'Catatan biaya',
        // additional_cost tidak dikirim → default 0
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.additional_cost', 0)
        ->assertJsonPath('data.additional_cost_note', 'Catatan biaya');
});

it('returns 401 when not authenticated on store', function () {
    $this->postJson('/api/v1/sales-transactions', $this->payload)
        ->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get sales transaction detail', function () {
    $transaction = SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/sales-transactions/{$transaction->ulid}")
        ->assertStatus(200)
        ->assertJsonPath('data.ulid', (string) $transaction->ulid);
});

it('returns 404 when transaction not found on show', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions/invalid-ulid')
        ->assertStatus(404);
});

it('returns 404 when accessing transaction from other company', function () {
    $otherCompany      = Company::factory()->create();
    $otherUser         = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherCustomerType = CustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);
    $otherCustomer     = Customer::factory()->create([
        'customer_type_id' => $otherCustomerType->id,
        'created_by'       => $otherUser->id,
        'company_id'       => $otherCompany->id,
    ]);

    $transaction = SalesTransaction::factory()->create([
        'customer_id' => $otherCustomer->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/sales-transactions/{$transaction->ulid}")
        ->assertStatus(404);
});

// =============================
// CANCEL
// =============================

it('can cancel a sales transaction', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $stockAfterSales = $this->product->fresh()->stock;

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel")
        ->assertStatus(200)
        ->assertJsonPath('data.transaction_status', TransactionStatus::CANCEL->value);

    expect($this->product->fresh()->stock)->toBe($stockAfterSales + 3);
});

it('stock returns to original after cancel', function () {
    $stockOriginal = $this->product->stock;

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel");

    expect($this->product->fresh()->stock)->toBe($stockOriginal);
});

it('cancel creates ADJUST_IN stock mutation', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel");

    $this->assertDatabaseHas('stock_mutations', [
        'product_id' => $this->product->id,
        'type'       => 'ADJUST_IN',
        'company_id' => $this->company->id,
    ]);
});

it('cancel creates ADJUST_IN for each item', function () {
    $payload = array_merge($this->payload, [
        'total' => 120000,
        'paid'  => 120000,
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 3,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
            [
                'product_uuid' => $this->product2->uuid,
                'quantity'     => 2,
                'sell_price'   => 25000,
                'discount'     => 0,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel");

    // 2 SALES_OUT + 2 ADJUST_IN
    $this->assertDatabaseCount('stock_mutations', 4);
});

it('returns 422 when cancelling already cancelled transaction', function () {
    $transaction = SalesTransaction::factory()->create([
        'transaction_status' => TransactionStatus::CANCEL,
        'customer_id'        => $this->customer->id,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('cancelled transaction cannot be cancelled again', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $this->payload);

    $ulid = $response->json('data.ulid');

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel");

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 404 when cancelling transaction from other company', function () {
    $otherCompany      = Company::factory()->create();
    $otherUser         = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherCustomerType = CustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);
    $otherCustomer     = Customer::factory()->create([
        'customer_type_id' => $otherCustomerType->id,
        'created_by'       => $otherUser->id,
        'company_id'       => $otherCompany->id,
    ]);

    $transaction = SalesTransaction::factory()->create([
        'customer_id' => $otherCustomer->id,
        'created_by'  => $otherUser->id,
        'company_id'  => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(404);
});

it('returns 401 when not authenticated on cancel', function () {
    $transaction = SalesTransaction::factory()->create([
        'customer_id' => $this->customer->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    $this->patchJson("/api/v1/sales-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(401);
});

it('returns 422 when cancelling non-PAID transaction', function () {
    $transaction = SalesTransaction::factory()->create([
        'transaction_status' => TransactionStatus::PENDING,
        'customer_id'        => $this->customer->id,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/sales-transactions/{$transaction->ulid}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

// =============================
// ADDITIONAL TESTS FOR COVERAGE
// =============================

it('can search by transaction_code', function () {
    SalesTransaction::factory()->create([
        'transaction_code' => 'SO-ABC12345-20260505',
        'customer_id'      => $this->customer->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);
    SalesTransaction::factory()->create([
        'transaction_code' => 'SO-XYZ98765-20260505',
        'customer_id'      => $this->customer->id,
        'created_by'       => $this->user->id,
        'company_id'       => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales-transactions?search=ABC');

    expect($response->json('data'))->toHaveCount(1);
});

it('rolls back when error occurs during store', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherProduct = Product::factory()->create(['company_id' => $otherCompany->id]);

    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $otherProduct->uuid,
                'quantity'     => 1,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);

    $this->assertDatabaseCount('sales_transactions', 0);
});

it('returns 422 when customer_uuid not found', function () {
    $payload = array_merge($this->payload, [
        'customer_uuid' => 'invalid-uuid',
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when total is negative', function () {
    $payload = array_merge($this->payload, ['total' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when paid is negative', function () {
    $payload = array_merge($this->payload, ['paid' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when discount is negative', function () {
    $payload = array_merge($this->payload, ['discount' => -1000]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when quantity is zero', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 0,
                'sell_price'   => 15000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when sell_price is negative', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 3,
                'sell_price'   => -1000,
                'discount'     => 0,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when item discount is negative', function () {
    $payload = array_merge($this->payload, [
        'items' => [
            [
                'product_uuid' => $this->product->uuid,
                'quantity'     => 3,
                'sell_price'   => 15000,
                'discount'     => -1000,
            ],
        ],
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

it('returns 422 when customer belongs to other company', function () {
    $otherCompany      = Company::factory()->create();
    $otherUser         = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherCustomerType = CustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
    ]);
    $otherCustomer     = Customer::factory()->create([
        'customer_type_id' => $otherCustomerType->id,
        'created_by'       => $otherUser->id,
        'company_id'       => $otherCompany->id,
    ]);

    $payload = array_merge($this->payload, [
        'customer_uuid' => $otherCustomer->uuid,
    ]);

    $this->actingAs($this->user)
        ->postJson('/api/v1/sales-transactions', $payload)
        ->assertStatus(422);
});

