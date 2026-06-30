<?php

use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use App\Models\PosProduct;
use App\Models\PosSalesTransaction;
use App\Models\PosUnit;
use App\Models\User;
use App\Enums\Role;
use App\Enums\PosTransactionStatus;
use App\Enums\PosPaymentType;
use Carbon\Carbon;

beforeEach(function () {
    $this->company      = Company::factory()->create();
    $this->user         = User::factory()->admin()->create([
        'company_id' => $this->company->id,
    ]);
    $this->otherCompany = Company::factory()->create();
    $this->otherUser    = User::factory()->owner()->create([
        'company_id' => $this->otherCompany->id,
    ]);
});

// =============================
// INDEX
// =============================

it('can get home dashboard with day period', function () {
    // Create test data
    PosProduct::factory(5)->create(['company_id' => $this->company->id]);
    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);
    $customerType = PosCustomerType::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->user->id]);
    PosCustomer::factory(3)->create(['customer_type_id' => $customerType->id, 'company_id' => $this->company->id, 'created_by' => $this->user->id]);

    // Create sales transaction for today
    PosSalesTransaction::factory()->create([
        'transaction_date'   => Carbon::now(),
        'total'              => 100000,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=day');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.period', 'day')
        ->assertJsonPath('data.total_products', 5)
        ->assertJsonPath('data.total_marketing', 2)
        ->assertJsonPath('data.total_customers', 3)
        ->assertJsonPath('data.total_sales_nominal', 100000)
        ->assertJsonPath('data.total_sales_transactions', 1);
});

it('can get home dashboard with month period', function () {
    PosProduct::factory(3)->create(['company_id' => $this->company->id]);
    User::factory(1)->marketing()->create(['company_id' => $this->company->id]);
    $customerType = PosCustomerType::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->user->id]);
    PosCustomer::factory(2)->create(['customer_type_id' => $customerType->id, 'company_id' => $this->company->id, 'created_by' => $this->user->id]);

    // Create sales transactions this month
    PosSalesTransaction::factory(2)->create([
        'transaction_date'   => Carbon::now()->startOfMonth()->addDays(5),
        'total'              => 50000,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=month');

    $response->assertStatus(200)
        ->assertJsonPath('data.period', 'month')
        ->assertJsonPath('data.total_products', 3)
        ->assertJsonPath('data.total_marketing', 1)
        ->assertJsonPath('data.total_customers', 2)
        ->assertJsonPath('data.total_sales_nominal', 100000)
        ->assertJsonPath('data.total_sales_transactions', 2);
});

it('can get home dashboard with year period', function () {
    PosProduct::factory(2)->create(['company_id' => $this->company->id]);
    User::factory(3)->marketing()->create(['company_id' => $this->company->id]);
    $customerType = PosCustomerType::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->user->id]);
    PosCustomer::factory(5)->create(['customer_type_id' => $customerType->id, 'company_id' => $this->company->id, 'created_by' => $this->user->id]);

    // Create sales transactions this year
    PosSalesTransaction::factory(3)->create([
        'transaction_date'   => Carbon::now()->startOfYear()->addMonths(3),
        'total'              => 75000,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=year');

    $response->assertStatus(200)
        ->assertJsonPath('data.period', 'year')
        ->assertJsonPath('data.total_products', 2)
        ->assertJsonPath('data.total_marketing', 3)
        ->assertJsonPath('data.total_customers', 5)
        ->assertJsonPath('data.total_sales_nominal', 225000)
        ->assertJsonPath('data.total_sales_transactions', 3);
});

it('defaults to day period when period is not provided', function () {
    PosProduct::factory(1)->create(['company_id' => $this->company->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home');

    $response->assertStatus(200)
        ->assertJsonPath('data.period', 'day');
});

it('returns 422 when period is invalid', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=invalid')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('only returns data for users company', function () {
    // Create data for this company
    PosProduct::factory(5)->create(['company_id' => $this->company->id]);
    User::factory(2)->marketing()->create(['company_id' => $this->company->id]);
    
    // Create data for other company
    PosProduct::factory(10)->create(['company_id' => $this->otherCompany->id]);
    User::factory(5)->marketing()->create(['company_id' => $this->otherCompany->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=day');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_products', 5)
        ->assertJsonPath('data.total_marketing', 2);
});

it('returns zero values when no data exists', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=day');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_products', 0)
        ->assertJsonPath('data.total_marketing', 0)
        ->assertJsonPath('data.total_customers', 0)
        ->assertJsonPath('data.total_sales_nominal', 0)
        ->assertJsonPath('data.total_sales_transactions', 0);
});

it('excludes sales transactions outside the period', function () {
    $customerType = PosCustomerType::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->user->id]);

    // Create transaction today
    PosSalesTransaction::factory()->create([
        'transaction_date'   => Carbon::now(),
        'total'              => 100000,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    // Create transaction yesterday
    PosSalesTransaction::factory()->create([
        'transaction_date'   => Carbon::now()->subDay(),
        'total'              => 50000,
        'created_by'         => $this->user->id,
        'company_id'         => $this->company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home?period=day');

    // Day period should only include today
    $response->assertStatus(200)
        ->assertJsonPath('data.total_sales_nominal', 100000)
        ->assertJsonPath('data.total_sales_transactions', 1);
});

it('returns 401 when not authenticated', function () {
    $this->getJson('/api/v1/pos/home')
        ->assertStatus(401);
});

it('response has correct json structure', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/pos/home');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'period',
                'total_products',
                'total_marketing',
                'total_customers',
                'total_sales_nominal',
                'total_sales_transactions',
            ],
        ]);
});
