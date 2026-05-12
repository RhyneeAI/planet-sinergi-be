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
    $this->customer = Customer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->owner->id,
        'company_id'       => $this->company->id,
    ]);
    $this->category = Category::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->unit = Unit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);

    // Product A: base 5000
    $this->productA = Product::factory()->create([
        'base_price'  => 5000,
        'sales_price' => 8000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Product B: base 12000
    $this->productB = Product::factory()->create([
        'base_price'  => 12000,
        'sales_price' => 18000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // marketing_price A = 6500 → komisi/unit = 1500
    // marketing_price B = 15000 → komisi/unit = 3000
    MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 6500,
        'company_id'      => $this->company->id,
    ]);

    MarketingProduct::factory()->create([
        'product_id'      => $this->productB->id,
        'marketing_id'    => $this->marketing->id,
        'marketing_price' => 15000,
        'company_id'      => $this->company->id,
    ]);
});

// Helper buat transaksi + detail
function makeSalesTrx(array $data): SalesTransaction
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
        'customer_id'        => $data['customer_id'] ?? null,
        'created_by'         => $data['created_by'], // ← marketing yang buat transaksi
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

it('returns 404 when marketing_uuid belongs to non-marketing role', function () {
    // Admin bukan marketing — seharusnya 404
    $admin = User::factory()->create([
        'role'       => Role::OWNER,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $admin->uuid)
        ->assertStatus(404);
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
     * Product A: qty 2, sales_price 8000, marketing_price 6500
     *   komisi = (8000 - 6500) * 2 = 3000
     * Product B: qty 3, sales_price 18000, marketing_price 15000
     *   komisi = (18000 - 15000) * 3 = 9000
     * Diskon = 0
     * Total komisi = 12000
    */
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => (2 * 6500) + (3 * 15000),
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
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

it('calculates commission correctly with discount (fully charged to marketing)', function () {
    /*
     * Product A: qty 2, sales_price 8000, marketing_price 6500
     *   komisi kotor = (8000 - 6500) * 2 = 3000
     * Diskon = 2000 (100% ditanggung marketing)
     * Komisi bersih = 3000 - 2000 = 1000
    */
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 2000,
        'total'       => (2 * 6500) - 2000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(1000);
});

it('commission is never negative even if discount exceeds gross commission', function () {
    /*
     * Komisi kotor = (6500-5000)*1 = 1500
     * Diskon = 10000 → komisi bersih = max(0, 1500-10000) = 0
     */
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 10000,
        'total'       => 0,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
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
     *   diskon = 2000 (100% marketing)
     *   komisi bersih = 3000 - 2000 = 1000
     *
     * Total = 3000 + 1000 = 4000
     */
    makeSalesTrx([
        'date'        => '2026-02-01',
        'discount'    => 0,
        'total'       => 2 * 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 2000,
        'total'       => 15000 - 2000,
        'created_by'  => $this->marketing->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 15000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(4000);
});

// =============================
// KALKULASI — MULTIPLE MARKETING
// =============================

it('calculates commission separately for each marketing', function () {
    MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000, // komisi = (8000-7000)*qty = 1000*qty
        'company_id'      => $this->company->id,
    ]);

    // Marketing 1: (8000-6500)*2 = 3000
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 2 * 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 6500],
        ],
    ]);

    // Marketing 2: (8000-7000)*3 = 3000
    makeSalesTrx([
        'date'        => '2026-03-05',
        'discount'    => 0,
        'total'       => 3 * 7000,
        'created_by'  => $this->marketing2->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(6000);
});

// =============================
// FILTER
// =============================

it('only includes transactions within date range', function () {
    // Dalam range
    makeSalesTrx([
        'date'        => '2026-03-15',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // Di luar range
    makeSalesTrx([
        'date'        => '2026-06-01',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-03-31');

    $response->assertStatus(200);
    // Hanya 1 transaksi: (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('filters by specific marketing_uuid', function () {
    MarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000,
        'company_id'      => $this->company->id,
    ]);

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    makeSalesTrx([
        'date'        => '2026-03-05',
        'discount'    => 0,
        'total'       => 7000,
        'created_by'  => $this->marketing2->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->marketing->uuid);

    $response->assertStatus(200);
    // Hanya marketing 1: (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('excludes transactions created by non-marketing role', function () {
    // Transaksi oleh owner — tidak boleh masuk laporan
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->owner->id, // ← owner bukan marketing
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});

it('excludes cancelled transactions from commission', function () {
    // PAID
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 6500,
        'status'      => TransactionStatus::PAID,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    // CANCEL — tidak masuk
    makeSalesTrx([
        'date'        => '2026-03-10',
        'discount'    => 0,
        'total'       => 6500,
        'status'      => TransactionStatus::CANCEL,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    // Hanya 1 PAID: (6500-5000)*1 = 1500
    expect($response->json('data.grand_total.total_commission'))->toEqual(1500);
});

it('returns zero when no transactions in range', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
    expect($response->json('data.grand_total.total_sales'))->toEqual(0);
    expect($response->json('data.grand_total.total_discount'))->toEqual(0);
});

it('skips commission for product not in marketing_products', function () {
    $productC = Product::factory()->create([
        'base_price'  => 8000,
        'sales_price' => 12000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 10000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $productC->id, 'qty' => 1, 'price' => 10000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});

it('returns correct response structure', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500],
        ],
    ]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31')
        ->assertStatus(200)
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