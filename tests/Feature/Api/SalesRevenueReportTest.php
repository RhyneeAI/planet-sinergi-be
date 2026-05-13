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
    $this->user         = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->cashier      = User::factory()->create([
        'role'       => Role::MARKETING,
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

    // Product A: sell 5000
    $this->productA = Product::factory()->create([
        'code'        => 'TEST-A',
        'sales_price' => 5000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
        'company_id'  => $this->company->id,
    ]);

    // Product B: sell 15000
    $this->productB = Product::factory()->create([
        'code'        => 'TEST-B',
        'sales_price' => 15000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->user->id,
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
            'ulid'       => Str::ulid(),
            'sale_id'    => $trx->id,
            'product_id' => $item['product_id'],
            'quantity'   => $item['qty'],
            'sell_price' => $item['price'],
            'discount'   => 0,
            'subtotal'   => $item['qty'] * $item['price'],
            'company_id' => $data['company_id'],
        ]);
    }

    return $trx;
}

// =============================
// VALIDASI
// =============================

it('returns 422 when date_from is missing', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_to=2026-05-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_from']]);
});

it('returns 422 when date_to is missing', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_to']]);
});

it('returns 422 when date_to is before date_from', function () {
    $this->actingAs($this->user)
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
    /*
     * Trx 1: Product A qty 3, price 5000 → revenue 15000
     *         Product B qty 2, price 15000 → revenue 30000
     * Total qty = 5, total revenue = 45000
     */
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 45000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
            ['product_id' => $this->productB->id, 'qty' => 2, 'price' => 15000],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(5);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(45000);
});

it('accumulates revenue correctly across multiple transactions', function () {
    /*
     * Trx 1: Product A qty 3 → 15000
     * Trx 2: Product A qty 5 → 25000
     * Product A total qty = 8, revenue = 40000
     */
    makeSalesRevTrx([
        'date'       => '2026-02-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 25000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(8);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(40000);
});

// =============================
// FILTER DATE RANGE
// =============================

it('only includes transactions within date range', function () {
    // Dalam range
    makeSalesRevTrx([
        'date'       => '2026-03-15',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    // Di luar range
    makeSalesRevTrx([
        'date'       => '2026-06-01',
        'total'      => 25000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-03-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(15000);
});

// =============================
// EXCLUDE CANCELLED
// =============================

it('excludes cancelled transactions', function () {
    // PAID
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'status'     => TransactionStatus::PAID,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    // CANCEL — tidak masuk
    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'status'     => TransactionStatus::CANCEL,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000],
        ],
    ]);

    $response = $this->actingAs($this->user)
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

    // Transaksi company sendiri
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    // Transaksi company lain — tidak masuk
    $otherProduct = Product::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherUser->id,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
    ]);

    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'created_by' => $otherUser->id,
        'company_id' => $otherCompany->id,
        'items'      => [
            ['product_id' => $otherProduct->id, 'qty' => 5, 'price' => 5000],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
});

// =============================
// ZERO DATA
// =============================

it('returns zero when no transactions in range', function () {
    $response = $this->actingAs($this->user)
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
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    $this->actingAs($this->user)
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
    /*
     * Product A sales_price = 5000
     * Tapi dijual dengan harga 7000 (harga saat transaksi)
     * Revenue harus = 7000 * 2 = 14000, bukan 5000 * 2 = 10000
     */
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 14000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->user)
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

    // Transaksi dari cashier pertama
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    // Transaksi dari cashier kedua
    makeSalesRevTrx([
        'date'       => '2026-03-05',
        'total'      => 25000,
        'created_by' => $anotherCashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 5, 'price' => 5000],
        ],
    ]);

    // Filter by first cashier
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->cashier->uuid);

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(3);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(15000);
});

it('returns 422 when marketing_uuid format is invalid', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=invalid-uuid')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing_uuid does not exist', function () {
    $fakeUuid = '550e8400-e29b-41d4-a716-446655440000';
    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $fakeUuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing_uuid is not a marketing user (e.g., owner)', function () {
    // Try to filter by owner user (not marketing role)
    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->user->uuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 422 when marketing belongs to different company', function () {
    $otherCompany = Company::factory()->create();
    $otherMarketing = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $otherCompany->id,
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $otherMarketing->uuid)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns zero when filtered marketing has no transactions', function () {
    $anotherCashier = User::factory()->create([
        'role'       => Role::MARKETING,
        'company_id' => $this->company->id,
    ]);

    // Create transaction by first cashier only
    makeSalesRevTrx([
        'date'       => '2026-03-01',
        'total'      => 15000,
        'created_by' => $this->cashier->id,
        'company_id' => $this->company->id,
        'items'      => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 5000],
        ],
    ]);

    // Filter by second cashier (no transactions)
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/sales-revenue?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $anotherCashier->uuid);

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_qty'))->toEqual(0);
    expect($response->json('data.grand_total.total_revenue'))->toEqual(0);
});