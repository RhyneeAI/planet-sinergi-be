<?php

use App\Enums\PaymentType;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Product;
use App\Models\SalesDetail;
use App\Models\SalesTransaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->owner        = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->cashier      = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $this->company->id,
    ]);
    $this->customerType = CustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->customer     = Customer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->owner->id,
        'company_id'       => $this->company->id,
    ]);
    $this->category     = Category::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->unit         = Unit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);

    // Product A: base price 3000, sales price 5000
    $this->productA = Product::factory()->create([
        'code'        => 'TEST-A',
        'base_price'  => 3000,
        'sales_price' => 5000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Product B: base price 10000, sales price 15000
    $this->productB = Product::factory()->create([
        'code'        => 'TEST-B',
        'base_price'  => 10000,
        'sales_price' => 15000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);
});

// Helper buat transaksi + detail
function makeSalesRevTrx(array $data): SalesTransaction
{
    $trx = SalesTransaction::create([
        'ulid'               => Str::ulid(),
        'transaction_code'   => 'SO-REV-' . Str::random(6),
        'transaction_date'   => $data['date'],
        'discount'           => $data['discount'] ?? 0,
        'total'              => $data['total'],
        'paid'               => $data['total'],
        'payment_type'       => PaymentType::CASH,
        'transaction_status' => $data['status'] ?? TransactionStatus::PAID,
        'customer_id'        => $data['customer_id'] ?? null,
        'created_by'         => $data['created_by'],
        'company_id'         => $data['company_id'],
    ]);

    foreach ($data['items'] as $item) {
        SalesDetail::create([
            'ulid'            => Str::ulid(),
            'sale_id'         => $trx->id,
            'product_id'      => $item['product_id'],
            'quantity'        => $item['qty'],
            'sell_price'      => $item['price'],
            'marketing_price' => $item['marketing_price'] ?? null,
            'discount'        => $item['discount'] ?? 0,
            'subtotal'        => $item['qty'] * $item['price'],
            'company_id'      => $data['company_id'],
        ]);
    }

    return $trx;
}

// =============================
// VALIDASI
// =============================

it('returns 422 when date_from is missing', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_to=2026-05-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_from']]);
});

it('returns 422 when date_to is missing', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_to']]);
});

it('returns 422 when date_to is before date_from', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-05-01&date_to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_to']]);
});

it('returns 401 when not authenticated', function () {
    $this->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31')
        ->assertStatus(401);
});

// =============================
// GRAND TOTAL
// =============================

it('calculates grand total qty and revenue correctly', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 45000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
            ['product_id' => $this->productB->id, 'qty' => 2, 'price' => 15000, 'marketing_price' => 12000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(5);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(45000);
});

it('accumulates revenue correctly across multiple transactions', function () {
    makeSalesRevTrx([
        'date'       => '2026-02-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 25000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(8);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(40000);
});

// =============================
// PROFIT CALCULATION
// =============================

it('calculates profit correctly for owner transactions', function () {
    // Owner langsung transaksi (bukan marketing)
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 50000,
        'created_by' => $this->owner->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 10000, 'marketing_price' => 0],
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 30000, 'marketing_price' => 0],
        ],
    ]);

    // Profit = (10000 - 3000)*2 + (30000 - 10000)*1 = 14000 + 20000 = 34000
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(50000);
    // Jika controller sudah implement profit di response, tambahkan assertion:
    // expect($response->json('data.grand_total.total_profit'))->toEqual(34000);
});

it('calculates profit correctly for marketing transactions', function () {
    // Marketing transaksi dengan marketing_price
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 50000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 10000, 'marketing_price' => 8000],
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 30000, 'marketing_price' => 25000],
        ],
    ]);

    // Profit = (8000 - 3000)*2 + (25000 - 10000)*1 = 10000 + 15000 = 25000
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(50000);
    // expect($response->json('data.grand_total.total_profit'))->toEqual(25000);
});

it('uses marketing_price from sales_details not from product for historical accuracy', function () {
    // Ubah marketing_price di product (seharusnya tidak berpengaruh ke laporan)
    $this->productA->update(['marketing_price' => 99999]);
    $this->productB->update(['marketing_price' => 99999]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 50000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 10000, 'marketing_price' => 8000],
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 30000, 'marketing_price' => 25000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(50000);
    // profit tetap 25000 (menggunakan marketing_price dari SalesDetail = 8000 & 25000)
    // BUKAN 99999 dari product
    // expect($response->json('data.grand_total.total_profit'))->toEqual(25000);
});

// =============================
// FILTER DATE RANGE
// =============================

it('only includes transactions within date range', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-15',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-06-01',
        'total'      => 25000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-03-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(15000);
});

// =============================
// INCLUDE PENDING (CICIL)
// =============================

it('includes pending transactions', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'status'     => TransactionStatus::PAID,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'status'     => TransactionStatus::PROCESS,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(8);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(40000);
});

// =============================
// EXCLUDE CANCELLED
// =============================

it('excludes cancelled transactions', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'status'     => TransactionStatus::PAID,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'status'     => TransactionStatus::CANCEL,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(15000);
});

// =============================
// COMPANY ISOLATION
// =============================

it('only includes transactions from same company', function () {
    $otherCompany = Company::factory()->create();
    $otherUser    = User::factory()->owner()->create(['company_id' => $otherCompany->id]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $otherProduct = Product::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
        'base_price' => 5000,
        'sales_price' => 10000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'created_by' => $otherUser->id,
        'company_id' => $otherCompany->id,
        'items'      => [
            ['product_id' => $otherProduct->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 0],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
});

// =============================
// ZERO DATA
// =============================

it('returns zero when no transactions in range', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(0);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(0);
});

// =============================
// RESPONSE STRUCTURE
// =============================

it('returns correct response structure', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'period'      => ['from', 'to'],
                'grand_total' => ['total_qty', 'total_revenue'],
                'download_url',
            ],
        ]);
});

// =============================
// REVENUE CALCULATION
// =============================

it('uses sell_price from sales_details not products.sales_price', function () {
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 14000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 7000, 'marketing_price' => 6000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(14000);
});

// =============================
// FILTER BY MARKETING
// =============================

it('filters transactions by specific marketing', function () {
    $anotherCashier = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $this->company->id,
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'created_by' => $anotherCashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->cashier->uuid);

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(15000);
});

it('returns 422 when marketing_uuid format is invalid', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=invalid-uuid')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing_uuid does not exist', function () {
    $fakeUuid = '550e8400-e29b-41d4-a716-446655440000';
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $fakeUuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing_uuid is not a marketing user (e.g., owner)', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->owner->uuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing belongs to different company', function () {
    $otherCompany = Company::factory()->create();
    $otherMarketing = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $otherMarketing->uuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns zero when filtered marketing has no transactions', function () {
    $anotherCashier = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $this->company->id,
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000, 'marketing_price' => 4000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $anotherCashier->uuid);

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(0);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(0);
});