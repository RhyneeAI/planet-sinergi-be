// tests/Feature/Api/MarketingCommissionReportTest.php
<?php

use App\Enums\PaymentType;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\MarketingProduct;
use App\Models\Product;
use App\Models\SalesDetail;
use App\Models\SalesTransaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->owner        = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->marketing    = User::factory()->marketing()->create(['company_id' => $this->company->id]);
    $this->marketing2   = User::factory()->marketing()->create(['company_id' => $this->company->id]);
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

    // Product A: base 5000, sales 8000
    $this->productA = Product::factory()->create([
        'base_price'  => 5000,
        'sales_price' => 8000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Product B: base 12000, sales 18000
    $this->productB = Product::factory()->create([
        'base_price'  => 12000,
        'sales_price' => 18000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Marketing price: marketing harga khusus
    // Product A: marketing_price 6500 (komisi per unit = 6500 - 5000 = 1500)
    // Product B: marketing_price 15000 (komisi per unit = 15000 - 12000 = 3000)
    $this->mpA = MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 6500,
        'company_id'      => $this->company->id,
    ]);

    $this->mpB = MarketingProduct::factory()->create([
        'product_id'      => $this->productB->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 15000,
        'company_id'      => $this->company->id,
    ]);
});

// Helper untuk buat transaksi beserta detailnya
function createSalesTransaction(array $data): SalesTransaction
{
    $trx = SalesTransaction::create([
        'ulid'               => Str::ulid(),
        'transaction_code'   => 'SO-TEST-' . Str::random(6),
        'transaction_date'   => $data['date'],
        'discount'           => $data['discount'] ?? 0,
        'total'              => $data['total'],
        'paid'               => $data['total'],
        'payment_type'       => $data['payment_type'] ?? PaymentType::CASH,
        'transaction_status' => $data['status'] ?? TransactionStatus::PAID,
        'marketing_id'       => $data['marketing_id'],
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
// VALIDASI REQUEST
// =============================

it('returns 422 when date_from is missing', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_to=2026-05-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_from']]);
});

it('returns 422 when date_to is missing', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_to']]);
});

it('returns 422 when date_to is before date_from', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-05-01&date_to=2026-01-01')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['date_to']]);
});

it('returns 422 when marketing_uuid not found', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . Str::uuid())
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['marketing_uuid']]);
});

it('returns 401 when not authenticated', function () {
    $this->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31')
        ->assertStatus(401);
});

// =============================
// KALKULASI KOMISI — TANPA DISKON
// =============================

it('calculates commission correctly without discount', function () {
    /*
     * Transaksi:
     * Product A: qty 2, marketing_price 6500, base_price 5000
     *   → komisi = (6500 - 5000) * 2 = 3000
     * Product B: qty 3, marketing_price 15000, base_price 12000
     *   → komisi = (15000 - 12000) * 3 = 9000
     *
     * Total komisi kotor = 3000 + 9000 = 12000
     * Diskon transaksi = 0
     * Porsi diskon marketing (50%) = 0
     * Komisi bersih = 12000
     */
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => (2 * 6500) + (3 * 15000), // 13000 + 45000 = 58000
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
            ['product_id' => $this->productB->id, 'qty' => 3, 'price' => 15000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(12000);
});

// =============================
// KALKULASI KOMISI — DENGAN DISKON
// =============================

it('calculates commission correctly with discount (50:50 split)', function () {
    /*
     * Transaksi:
     * Product A: qty 2, marketing_price 6500, base_price 5000
     *   → komisi kotor = (6500 - 5000) * 2 = 3000
     * Diskon transaksi = 2000
     * Porsi diskon marketing (50%) = 2000 * 50% = 1000
     * Komisi bersih = 3000 - 1000 = 2000
     */
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 2000,
        'total'        => (2 * 6500) - 2000, // 11000
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

it('commission is never negative even if discount is very large', function () {
    /*
     * Komisi kotor = (6500 - 5000) * 1 = 1500
     * Diskon = 10000, porsi marketing = 5000
     * Komisi bersih = max(0, 1500 - 5000) = 0
     */
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 10000,
        'total'        => 0,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});

// =============================
// KALKULASI — MULTIPLE TRANSAKSI
// =============================

it('accumulates commission correctly across multiple transactions', function () {
    /*
     * Transaksi 1: Product A qty 2, no discount
     *   komisi = (6500-5000)*2 = 3000
     *
     * Transaksi 2: Product B qty 1, discount 2000
     *   komisi kotor = (15000-12000)*1 = 3000
     *   porsi diskon = 2000 * 50% = 1000
     *   komisi bersih = 3000 - 1000 = 2000
     *
     * Total komisi = 3000 + 2000 = 5000
     */
    createSalesTransaction([
        'date'         => '2026-02-01',
        'discount'     => 0,
        'total'        => 2 * 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 2000,
        'total'        => 15000 - 2000,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => null,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 15000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(5000);
});

// =============================
// KALKULASI — MULTIPLE MARKETING
// =============================

it('calculates commission separately for each marketing', function () {
    // Marketing 2 punya harga berbeda untuk Product A
    MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000, // komisi = (7000-5000)*qty
        'company_id'      => $this->company->id,
    ]);

    // Marketing 1: Product A qty 2
    // komisi = (6500-5000)*2 = 3000
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 2 * 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    // Marketing 2: Product A qty 3
    // komisi = (7000-5000)*3 = 6000
    createSalesTransaction([
        'date'         => '2026-03-05',
        'discount'     => 0,
        'total'        => 3 * 7000,
        'marketing_id' => $this->marketing2->id,
        'customer_id'  => null,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    // Grand total = 3000 + 6000 = 9000
    expect($response->json('data.grand_total.total_commission'))->toEqual(9000);
});

// =============================
// FILTER
// =============================

it('only includes transactions within date range', function () {
    // Dalam range
    createSalesTransaction([
        'date'         => '2026-03-15',
        'discount'     => 0,
        'total'        => 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // Di luar range
    createSalesTransaction([
        'date'         => '2026-06-01',
        'discount'     => 0,
        'total'        => 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-03-31');

    $response->assertStatus(200);

    // Hanya 1 transaksi: komisi = (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('filters by specific marketing_uuid', function () {
    // Marketing 2 punya produk
    MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000,
        'company_id'      => $this->company->id,
    ]);

    // Marketing 1 transaksi
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // Marketing 2 transaksi
    createSalesTransaction([
        'date'         => '2026-03-05',
        'discount'     => 0,
        'total'        => 7000,
        'marketing_id' => $this->marketing2->id,
        'customer_id'  => null,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 7000],
        ],
    ]);

    // Filter hanya marketing 1
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->marketing->uuid);

    $response->assertStatus(200);

    // Hanya komisi marketing 1: (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('excludes cancelled transactions from commission', function () {
    // Transaksi PAID
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 6500,
        'status'       => TransactionStatus::PAID,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // Transaksi CANCEL — tidak boleh masuk komisi
    createSalesTransaction([
        'date'         => '2026-03-10',
        'discount'     => 0,
        'total'        => 6500,
        'status'       => TransactionStatus::CANCEL,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    // Hanya 1 transaksi PAID: (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('excludes transactions without marketing_id', function () {
    // Transaksi tanpa marketing — tidak masuk laporan
    SalesTransaction::create([
        'ulid'               => Str::ulid(),
        'transaction_code'   => 'SO-NO-MKT',
        'transaction_date'   => '2026-03-01',
        'discount'           => 0,
        'total'              => 6500,
        'paid'               => 6500,
        'payment_type'       => PaymentType::CASH,
        'transaction_status' => TransactionStatus::PAID,
        'marketing_id'       => null, // ← tidak ada marketing
        'customer_id'        => $this->customer->id,
        'created_by'         => $this->owner->id,
        'company_id'         => $this->company->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});

it('returns zero commission when no transactions in range', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);

    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
    expect($response->json('data.grand_total.total_sales'))->toEqual(0);
    expect($response->json('data.grand_total.total_discount'))->toEqual(0);
});

// =============================
// RESPONSE STRUCTURE
// =============================

it('returns correct response structure', function () {
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'period'      => ['from', 'to'],
                'grand_total' => ['total_sales', 'total_discount', 'total_commission'],
                'download_url',
            ],
        ]);
});

it('shows Umum when customer is null', function () {
    // Transaksi tanpa customer
    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 6500,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => null,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // Kita tidak bisa langsung cek PDF, tapi bisa cek response sukses
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
});

it('skips commission for product not in marketing_products', function () {
    /*
     * Product C tidak ada di marketing_products marketing ini
     * → komisi untuk product C = 0, di-skip
     * → total komisi = 0
     */
    $productC = Product::factory()->create([
        'base_price'  => 8000,
        'sales_price' => 12000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Tidak ada MarketingProduct untuk productC

    createSalesTransaction([
        'date'         => '2026-03-01',
        'discount'     => 0,
        'total'        => 10000,
        'marketing_id' => $this->marketing->id,
        'customer_id'  => $this->customer->id,
        'created_by'   => $this->owner->id,
        'company_id'   => $this->company->id,
        'items'        => [
            ['product_id' => $productC->id, 'qty' => 1, 'price' => 10000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});