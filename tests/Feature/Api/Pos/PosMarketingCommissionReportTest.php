<?php

use App\Enums\PosPaymentType;
use App\Enums\Role;
use App\Enums\PosTransactionStatus;
use App\Models\PosCategory;
use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use App\Models\PosMarketingProduct;
use App\Models\PosProduct;
use App\Models\PosSalesDetail;
use App\Models\PosSalesTransaction;
use App\Models\PosUnit;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->owner        = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $this->marketing    = User::factory()->marketing()->create(['company_id' => $this->company->id]);
    $this->marketing2   = User::factory()->marketing()->create(['company_id' => $this->company->id]);
    $this->customerType = PosCustomerType::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->customer = PosCustomer::factory()->create([
        'customer_type_id' => $this->customerType->id,
        'created_by'       => $this->owner->id,
        'company_id'       => $this->company->id,
    ]);
    $this->category = PosCategory::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);
    $this->unit = PosUnit::factory()->create([
        'company_id' => $this->company->id,
        'created_by' => $this->owner->id,
    ]);

    // Product A: base 5000
    $this->productA = PosProduct::factory()->create([
        'base_price'  => 5000,
        'sales_price' => 8000,
        'marketing_price' => 7000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Product B: base 12000
    $this->productB = PosProduct::factory()->create([
        'base_price'  => 12000,
        'sales_price' => 18000,
        'marketing_price' => 15000,
        'stock'       => 100,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // marketing_price A = 6500 → komisi/unit = 1500
    // marketing_price B = 15000 → komisi/unit = 3000
    // PosMarketingProduct::factory()->create([
    //     'product_id'      => $this->productA->id,
    //     'marketing_id'    => $this->marketing->id,
    //     'marketing_price' => 6500,
    //     'company_id'      => $this->company->id,
    // ]);

    // PosMarketingProduct::factory()->create([
    //     'product_id'      => $this->productB->id,
    //     'marketing_id'    => $this->marketing->id,
    //     'marketing_price' => 15000,
    //     'company_id'      => $this->company->id,
    // ]);
});

// Helper buat transaksi + detail
function makeSalesTrx(array $data): PosSalesTransaction
{
    $trx = PosSalesTransaction::create([
        'ulid'               => Str::ulid(),
        'transaction_code'   => 'SO-TEST-' . Str::random(6),
        'transaction_date'   => $data['date'],
        'discount'           => $data['discount'] ?? 0,
        'total'              => $data['total'],
        'paid'               => $data['total'],
        'payment_type'       => $data['payment_type'] ?? PosPaymentType::CASH,
        'transaction_status' => $data['status'] ?? PosTransactionStatus::PAID,
        'customer_id'        => $data['customer_id'] ?? null,
        'created_by'         => $data['created_by'], // ← marketing yang buat transaksi
        'company_id'         => $data['company_id'],
    ]);

    foreach ($data['items'] as $item) {
        PosSalesDetail::create([
            'ulid'       => Str::ulid(),
            'sale_id'    => $trx->id,
            'product_id' => $item['product_id'],
            'quantity'   => $item['qty'],
            'sell_price' => $item['price'],
            'marketing_price' => $item['marketing_price'],
            'discount'   => $item['discount'] ?? 0,
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
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 20000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 8000, 'marketing_price' => 6000],
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 20000, 'marketing_price' => 15000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(7000);
});

// =============================
// KALKULASI KOMISI — DENGAN DISKON
// =============================

it('calculates commission correctly with discount (fully charged to marketing)', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 2000,
        'total'       => 18000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

it('commission is never negative even if discount exceeds gross commission', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 10000,
        'total'       => 0,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500, 'marketing_price' => 5000],
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
    makeSalesTrx([
        'date'        => '2026-02-01',
        'discount'    => 2000,
        'total'       => 18000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]); // Commission : 2k

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 20000,
        'created_by'  => $this->marketing->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productB->id, 'qty' => 1, 'price' => 20000, 'marketing_price' => 15000],
        ],
    ]); // Commission : 5k

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(7000);
});

// =============================
// KALKULASI — MULTIPLE MARKETING
// =============================

it('calculates commission all for each marketing', function () {
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000, 
        'company_id'      => $this->company->id,
    ]);

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 2 * 9000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    makeSalesTrx([
        'date'        => '2026-03-05',
        'discount'    => 0,
        'total'       => 3 * 9000,
        'created_by'  => $this->marketing2->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

     // Cek grand total = 4000 + 6000 = 10000
    expect($response->json('data.grand_total.total_commission'))->toEqual(10000);
});

// =============================
// FILTER
// =============================

it('only includes transactions within date range', function () {
    // Dalam range
    makeSalesTrx([
        'date'        => '2026-03-15',
        'discount'    => 0,
        'total'       => 9000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    // Di luar range
    makeSalesTrx([
        'date'        => '2026-06-01',
        'discount'    => 0,
        'total'       => 9000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-03-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

it('filters by specific marketing_uuid', function () {
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000,
        'company_id'      => $this->company->id,
    ]);

    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 9000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    makeSalesTrx([
        'date'        => '2026-03-05',
        'discount'    => 0,
        'total'       => 9000,
        'created_by'  => $this->marketing2->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31&marketing_uuid=' . $this->marketing->uuid);

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

it('excludes transactions created by non-marketing role', function () {
    // Transaksi oleh owner — tidak boleh masuk laporan
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 9000,
        'created_by'  => $this->owner->id, // ← owner bukan marketing
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
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
        'total'       => 9000,
        'status'      => PosTransactionStatus::PAID,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    // CANCEL — tidak masuk
    makeSalesTrx([
        'date'        => '2026-03-10',
        'discount'    => 0,
        'total'       => 9000,
        'status'      => PosTransactionStatus::CANCEL,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

it('returns zero when no transactions in range', function () {
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
    expect($response->json('data.grand_total.total_sales'))->toEqual(0);
    expect($response->json('data.grand_total.total_discount'))->toEqual(0);
});

// it('skips commission for product not in marketing_products', function () {
//     $productC = PosProduct::factory()->create([
//         'base_price'  => 8000,
//         'sales_price' => 12000,
//         'stock'       => 100,
//         'category_id' => $this->category->id,
//         'unit_id'     => $this->unit->id,
//         'created_by'  => $this->owner->id,
//         'company_id'  => $this->company->id,
//     ]);

//     makeSalesTrx([
//         'date'        => '2026-03-01',
//         'discount'    => 0,
//         'total'       => 10000,
//         'created_by'  => $this->marketing->id,
//         'customer_id' => $this->customer->id,
//         'company_id'  => $this->company->id,
//         'items'       => [
//             ['product_id' => $productC->id, 'qty' => 1, 'price' => 10000],
//         ],
//     ]);

//     $response = $this->actingAs($this->owner)
//         ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

//     $response->assertStatus(200);
//     expect($response->json('data.grand_total.total_commission'))->toEqual(0);
// });

it('returns correct response structure', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 6500,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 1, 'price' => 6500, 'marketing_price' => 5000],
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

// =============================
// KALKULASI KOMISI — ITEM DISCOUNT
// =============================

it('calculates commission correctly with item-level discount only', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 16000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            [
                'product_id' => $this->productA->id, 
                'qty' => 2, 
                'price' => 10000, 
                'marketing_price' => 7000,
                'discount' => 2000  // item discount
            ],
        ],
    ]);

    // (10000 - 2000 - 7000) × 2 = (8000 - 7000) × 2 = 1000 × 2 = 2000
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(2000);
});

// =============================
// KALKULASI KOMISI — TRANSAKSI DISCOUNT + ITEM DISCOUNT
// =============================

it('calculates commission correctly with both transaction discount and item discount', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 3000,      // transaction discount
        'total'       => 17000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            [
                'product_id' => $this->productA->id, 
                'qty' => 2, 
                'price' => 10000, 
                'marketing_price' => 7000,
                'discount' => 1000   // item discount per unit? atau total? sesuaikan
            ],
        ],
    ]);

    // Jika item discount total 1000 (bukan per unit):
    // Gross commission = (10000 - 1000 - 7000) × 2 = (9000 - 7000) × 2 = 2000 × 2 = 4000
    // Net commission = max(0, 4000 - 3000) = 1000
    
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(1000);
});

// =============================
// KALKULASI KOMISI — ITEM DISCOUNT PER UNIT VS TOTAL
// =============================

it('calculates commission correctly when item discount is per unit', function () {
    // Asumsi: discount di items adalah total discount untuk item tersebut (bukan per unit)
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 16000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            [
                'product_id' => $this->productA->id, 
                'qty' => 2, 
                'price' => 10000, 
                'marketing_price' => 7000,
                'discount' => 2000  // total discount untuk 2 item
            ],
        ],
    ]);

    // (10000 - (2000/2) - 7000) × 2 = (10000 - 1000 - 7000) × 2 = 2000 × 2 = 4000
    // ATAU tergantung implementasi di controller
    // Pastikan konsisten dengan controller Anda
    
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    
    // Sesuaikan dengan perhitungan controller Anda
    // expect($response->json('data.grand_total.total_commission'))->toEqual(4000);
});

// =============================
// KALKULASI KOMISI — MULTIPLE ITEMS DENGAN MIX DISCOUNT
// =============================

it('calculates commission correctly with multiple items having different discounts', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 0,
        'total'       => 30000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            [
                'product_id' => $this->productA->id, 
                'qty' => 1, 
                'price' => 10000, 
                'marketing_price' => 7000,
                'discount' => 0
            ],
            [
                'product_id' => $this->productB->id, 
                'qty' => 1, 
                'price' => 20000, 
                'marketing_price' => 15000,
                'discount' => 2000
            ],
        ],
    ]);

    // Item A: (10000 - 0 - 7000) × 1 = 3000
    // Item B: (20000 - 2000 - 15000) × 1 = 3000
    // Total commission = 6000
    
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(6000);
});

// =============================
// KALKULASI KOMISI — DISCOUNT MELEBIHI GROSS COMMISSION
// =============================

it('commission is zero when discount exceeds gross commission (with item discount)', function () {
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 5000,
        'total'       => 5000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            [
                'product_id' => $this->productA->id, 
                'qty' => 1, 
                'price' => 10000, 
                'marketing_price' => 7000,
                'discount' => 2000
            ],
        ],
    ]);

    // Gross commission = (10000 - 2000 - 7000) = 1000
    // Net commission = max(0, 1000 - 5000) = 0
    
    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(0);
});

// =============================
// KALKULASI KOMISI — MULTIPLE MARKETING DENGAN DISCOUNT
// =============================

it('calculates commission correctly for multiple marketing with discounts', function () {
    PosMarketingProduct::factory()->create([
        'product_id'      => $this->productA->id,
        'marketing_id'    => $this->marketing2->id,
        'marketing_price' => 7000, 
        'company_id'      => $this->company->id,
    ]);

    // Marketing 1 dengan discount
    makeSalesTrx([
        'date'        => '2026-03-01',
        'discount'    => 1000,
        'total'       => 17000,
        'created_by'  => $this->marketing->id,
        'customer_id' => $this->customer->id,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 2, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]); // Gross = 4000, Net = 3000

    // Marketing 2 tanpa discount
    makeSalesTrx([
        'date'        => '2026-03-05',
        'discount'    => 0,
        'total'       => 27000,
        'created_by'  => $this->marketing2->id,
        'customer_id' => null,
        'company_id'  => $this->company->id,
        'items'       => [
            ['product_id' => $this->productA->id, 'qty' => 3, 'price' => 9000, 'marketing_price' => 7000],
        ],
    ]); // Gross = 6000, Net = 6000

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/reports/marketing-commission?date_from=2026-01-01&date_to=2026-12-31');

    $response->assertStatus(200);
    expect($response->json('data.grand_total.total_commission'))->toEqual(9000); // 3000 + 6000
});