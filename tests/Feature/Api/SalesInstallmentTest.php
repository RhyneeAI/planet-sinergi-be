<?php

use App\Enums\InstallmentStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Product;
use App\Models\SalesInstallmentPlan;
use App\Models\SalesTransaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->owner        = User::factory()->owner()->create(['company_id' => $this->company->id]);
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
    $this->product      = Product::factory()->create([
        'stock'       => 100,
        'sales_price' => 10000,
        'category_id' => $this->category->id,
        'unit_id'     => $this->unit->id,
        'created_by'  => $this->owner->id,
        'company_id'  => $this->company->id,
    ]);

    // Helper buat plan cicilan
    $this->makePlan = function (float $total = 300000, int $tenor = 3) {
        $trx = SalesTransaction::factory()->create([
            'total'              => $total,
            'paid'               => 0,
            'payment_type'       => PaymentType::CICIL,
            'transaction_status' => TransactionStatus::PENDING,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->owner->id,
            'company_id'         => $this->company->id,
        ]);

        return SalesInstallmentPlan::create([
            'ulid'                 => Str::ulid(),
            'sales_transaction_id' => $trx->id,
            'customer_id'          => $this->customer->id,
            'total_amount'         => $total,
            'paid_amount'          => 0,
            'tenor'                => $tenor,
            'start_date'           => now()->toDateString(),
            'status'               => InstallmentStatus::ACTIVE,
            'company_id'           => $this->company->id,
        ]);
    };
});

// =============================
// INDEX
// =============================

it('can get installment plan list', function () {
    ($this->makePlan)();
    ($this->makePlan)();

    $this->actingAs($this->owner)
        ->getJson('/api/v1/sales-installments')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success', 'message',
            'data' => ['*' => ['ulid', 'status', 'total_amount', 'paid_amount', 'remaining']]
        ]);
});

it('can filter by status', function () {
    $plan = ($this->makePlan)();
    $plan->update(['status' => InstallmentStatus::COMPLETED]);
    ($this->makePlan)(); // ACTIVE

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/sales-installments?status=COMPLETED');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('returns 401 when not authenticated', function () {
    $this->getJson('/api/v1/sales-installments')->assertStatus(401);
});

// =============================
// SHOW
// =============================

it('can get installment plan detail', function () {
    $plan = ($this->makePlan)();

    $this->actingAs($this->owner)
        ->getJson("/api/v1/sales-installments/{$plan->ulid}")
        ->assertStatus(200)
        ->assertJsonPath('data.ulid', (string) $plan->ulid);
});

it('returns 404 when plan not found', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/sales-installments/invalid-ulid')
        ->assertStatus(404);
});

// =============================
// PAY
// =============================

it('can record installment payment', function () {
    $plan = ($this->makePlan)(300000, 3);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", [
            'paid_amount' => 100000,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($plan->fresh()->paid_amount)->toEqual(100000);
    expect($plan->fresh()->status)->toEqual(InstallmentStatus::ACTIVE);
});

it('installment_number increments on each payment', function () {
    $plan = ($this->makePlan)(300000, 3);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 50000]);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 50000]);

    $payments = $plan->fresh()->payments;
    expect($payments[0]->installment_number)->toBe(1);
    expect($payments[1]->installment_number)->toBe(2);
});

it('status becomes COMPLETED when fully paid', function () {
    $plan = ($this->makePlan)(300000, 3);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 300000]);

    expect($plan->fresh()->status)->toEqual(InstallmentStatus::COMPLETED);
    expect($plan->fresh()->paid_amount)->toEqual(300000.0);
});

it('sales transaction status becomes PAID when installment completed', function () {
    $plan = ($this->makePlan)(300000, 3);
    $trx  = $plan->salesTransaction;

    expect($trx->transaction_status)->toEqual(TransactionStatus::PENDING);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 300000]);

    expect($trx->fresh()->transaction_status)->toEqual(TransactionStatus::PAID);
    expect($trx->fresh()->paid)->toEqual(300000.0);
});

it('can pay in multiple small installments', function () {
    $plan = ($this->makePlan)(300000, 3);

    // Bayar 30x @10000
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($this->owner)
            ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 10000]);
    }

    expect($plan->fresh()->status)->toEqual(InstallmentStatus::COMPLETED);
    expect($plan->fresh()->payments()->count())->toBe(30);
});

it('returns 422 when payment exceeds remaining', function () {
    $plan = ($this->makePlan)(300000, 3);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 400000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('returns 422 when paying already completed installment', function () {
    $plan = ($this->makePlan)(300000, 3);
    $plan->update(['status' => InstallmentStatus::COMPLETED, 'paid_amount' => 300000]);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 1000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('must pay full remaining when overdue', function () {
    // Buat plan yang sudah overdue (tenor habis)
    $plan = SalesInstallmentPlan::create([
        'ulid'                 => Str::ulid(),
        'sales_transaction_id' => SalesTransaction::factory()->create([
            'payment_type'       => PaymentType::CICIL,
            'transaction_status' => TransactionStatus::PENDING,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->owner->id,
            'company_id'         => $this->company->id,
        ])->id,
        'customer_id'  => $this->customer->id,
        'total_amount' => 300000,
        'paid_amount'  => 100000,
        'tenor'        => 3,
        'start_date'   => now()->subMonths(4)->toDateString(), // ← sudah lewat 4 bulan, tenor 3
        'status'       => InstallmentStatus::OVERDUE,
        'company_id'   => $this->company->id,
    ]);

    // Bayar kurang dari sisa (200000) → harus ditolak
    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 50000])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    // Bayar penuh sisa → berhasil
    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 200000])
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

it('returns 422 when paid_amount is zero', function () {
    $plan = ($this->makePlan)();

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$plan->ulid}/pay", ['paid_amount' => 0])
        ->assertStatus(422);
});

it('returns 404 when plan from other company', function () {
    $otherCompany = Company::factory()->create();
    $otherOwner   = User::factory()->owner()->create(['company_id' => $otherCompany->id]);
    $otherCT      = CustomerType::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => $otherOwner->id,
    ]);
    $otherCustomer = Customer::factory()->create([
        'customer_type_id' => $otherCT->id,
        'created_by'       => $otherOwner->id,
        'company_id'       => $otherCompany->id,
    ]);

    $otherPlan = SalesInstallmentPlan::create([
        'ulid'                 => Str::ulid(),
        'sales_transaction_id' => SalesTransaction::factory()->create([
            'payment_type'       => PaymentType::CICIL,
            'transaction_status' => TransactionStatus::PENDING,
            'customer_id'        => $otherCustomer->id,
            'created_by'         => $otherOwner->id,
            'company_id'         => $otherCompany->id,
        ])->id,
        'customer_id'  => $otherCustomer->id,
        'total_amount' => 100000,
        'paid_amount'  => 0,
        'tenor'        => 3,
        'start_date'   => now()->toDateString(),
        'status'       => InstallmentStatus::ACTIVE,
        'company_id'   => $otherCompany->id,
    ]);

    $this->actingAs($this->owner)
        ->postJson("/api/v1/sales-installments/{$otherPlan->ulid}/pay", ['paid_amount' => 10000])
        ->assertStatus(404);
});