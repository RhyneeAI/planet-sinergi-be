<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\OpsIncome;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
    $this->kepalaMandor = User::factory()->kepalaMandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->subCompany = SubCompany::where('mandor_id', $this->mandor->id)->first();

    $this->kepalaSubCompany = SubCompany::factory()->create([
        'mandor_id' => $this->kepalaMandor->id,
        'company_id' => $this->company->id,
    ]);
});

it('kepala mandor can access admin dashboard', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/dashboard/admin')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can access mandor dashboard', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/dashboard/mandor')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can list mandors', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can store income', function () {
    $this->actingAs($this->kepalaMandor)
        ->postJson('/api/v1/operational/incomes', [
            'name' => 'Pemasukan KP',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'sub_company_uuid' => $this->kepalaSubCompany->uuid,
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);
});

it('kepala mandor can store expense', function () {
    // Fund wallet first
    $this->actingAs($this->kepalaMandor)
        ->postJson('/api/v1/operational/incomes', [
            'name' => 'Saldo Awal',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'sub_company_uuid' => $this->kepalaSubCompany->uuid,
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ])
        ->assertCreated();

    $this->actingAs($this->kepalaMandor)
        ->postJson('/api/v1/operational/expenses', [
            'name' => 'Pengeluaran KP',
            'amount' => 50000,
            'date' => now()->toDateString(),
            'payment_method' => 'CASH',
            'sub_company_uuid' => $this->kepalaSubCompany->uuid,
            'proof_files' => [UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')],
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);
});

it('kepala mandor can access wallet', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/wallet?sub_company_uuid=' . $this->kepalaSubCompany->uuid)
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can access transfer confirmations', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/transfer-confirmations')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can access income-expense report', function () {
    OpsIncome::factory()->create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'amount' => 100000,
    ]);

    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/reports/income-expense?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor can access income-expense detail without mandor_uuid', function () {
    OpsIncome::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => $this->mandor->id,
        'date' => now()->toDateString(),
        'amount' => 100000,
    ]);

    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/reports/income-expense/detail?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor detail includes mandor data when no mandor_uuid', function () {
    OpsIncome::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => $this->mandor->id,
        'date' => now()->toDateString(),
        'amount' => 100000,
    ]);

    $response = $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/reports/income-expense/detail?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]));

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
});

it('admin can see internal data in detail without mandor_uuid', function () {
    OpsIncome::factory()->create([
        'company_id' => $this->company->id,
        'mandor_id' => null,
        'date' => now()->toDateString(),
        'amount' => 100000,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/reports/income-expense/detail?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]));

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
});

it('kepala mandor can access notifications', function () {
    $this->actingAs($this->kepalaMandor)
        ->getJson('/api/v1/operational/notifications')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('kepala mandor is in mandor list', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/operational/mandors')
        ->assertOk()
        ->assertJsonFragment(['uuid' => $this->kepalaMandor->uuid]);
});
