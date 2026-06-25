<?php

namespace Database\Seeders;

use App\Enums\PosPaymentType;
use App\Enums\Role;
use App\Models\PosCategory;
use App\Models\Company;
use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use App\Models\PosMarketingProduct;
use App\Models\PosProduct;
use App\Models\PosUnit;
use App\Models\User;
use Database\Seeders\Concerns\SeedsSalesTransactions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosMarketingCommissionReportSeeder extends Seeder
{
    use SeedsSalesTransactions;

    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'TMJ-001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Toko Maju Jaya',
                'address' => 'Jl. Komisi Demo, Jakarta',
            ]
        );

        $owner = User::updateOrCreate(
            ['phone' => '081990000001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Owner TMJ',
                'email' => 'owner@tmj.test',
                'password' => Hash::make('owner_tmj'),
                'role' => Role::OWNER,
                'company_id' => $company->id,
            ]
        );

        $abdillah = User::updateOrCreate(
            ['phone' => '081990000002'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Abdillah',
                'email' => 'abdillah@tmj.test',
                'password' => Hash::make('password'),
                'role' => Role::MARKETING,
                'company_id' => $company->id,
            ]
        );

        $ahmad = User::updateOrCreate(
            ['phone' => '081990000003'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Ahmad',
                'email' => 'ahmad@tmj.test',
                'password' => Hash::make('password'),
                'role' => Role::MARKETING,
                'company_id' => $company->id,
            ]
        );

        $category = PosCategory::firstOrCreate(
            ['name' => 'Produk Umum', 'company_id' => $company->id],
            ['uuid' => (string) Str::uuid(), 'created_by' => $owner->id]
        );

        $unit = PosUnit::firstOrCreate(
            ['name' => 'Pcs', 'company_id' => $company->id],
            ['uuid' => (string) Str::uuid(), 'created_by' => $owner->id]
        );

        $customerType = PosCustomerType::firstOrCreate(
            ['type' => 'Regular', 'company_id' => $company->id],
            ['uuid' => (string) Str::uuid(), 'discount' => 0, 'created_by' => $owner->id]
        );

        $andi = PosCustomer::firstOrCreate(
            ['name' => 'Andi', 'company_id' => $company->id],
            ['uuid' => (string) Str::uuid(), 'customer_type_id' => $customerType->id, 'created_by' => $owner->id]
        );

        $products = collect([
            ['name' => 'Lifebuoy', 'code' => 'TMJ-001', 'base' => 5000, 'sell' => 8000, 'marketing' => 6500, 'stock' => 100],
            ['name' => 'Shampoo Clear', 'code' => 'TMJ-002', 'base' => 12000, 'sell' => 18000, 'marketing' => 15000, 'stock' => 80],
            ['name' => 'Rinso', 'code' => 'TMJ-003', 'base' => 10000, 'sell' => 14000, 'marketing' => 12000, 'stock' => 60],
        ])->mapWithKeys(function (array $data) use ($company, $owner, $category, $unit) {
            $product = PosProduct::updateOrCreate(
                ['code' => $data['code'], 'company_id' => $company->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'base_price' => $data['base'],
                    'leader_price' => $data['sell'],
                    'marketing_price' => $data['marketing'],
                    'stock' => $data['stock'],
                    'is_active' => true,
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'created_by' => $owner->id,
                ]
            );

            return [$data['code'] => $product];
        });

        foreach ([$abdillah, $ahmad] as $marketing) {
            foreach ($products as $product) {
                PosMarketingProduct::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'marketing_id' => $marketing->id,
                        'company_id' => $company->id,
                    ],
                    ['uuid' => (string) Str::uuid(), 'created_by' => $owner->id]
                );
            }
        }

        $this->seedSalesTransactions($company, $products->all(), [
            [
                'date' => '2026-01-15',
                'customer_id' => $andi->id,
                'payment' => PosPaymentType::CASH,
                'discount' => 0,
                'created_by' => $abdillah->id,
                'items' => [
                    ['code' => 'TMJ-001', 'qty' => 5, 'price' => 6500],
                    ['code' => 'TMJ-003', 'qty' => 2, 'price' => 12000],
                ],
            ],
            [
                'date' => '2026-02-10',
                'customer_id' => null,
                'payment' => PosPaymentType::TRANSFER,
                'discount' => 2000,
                'created_by' => $abdillah->id,
                'items' => [
                    ['code' => 'TMJ-002', 'qty' => 3, 'price' => 15000],
                ],
            ],
            [
                'date' => '2026-01-08',
                'customer_id' => $andi->id,
                'payment' => PosPaymentType::CASH,
                'discount' => 0,
                'created_by' => $ahmad->id,
                'items' => [
                    ['code' => 'TMJ-001', 'qty' => 8, 'price' => 6000],
                ],
            ],
            [
                'date' => '2026-03-22',
                'customer_id' => null,
                'payment' => PosPaymentType::QRIS,
                'discount' => 0,
                'created_by' => $ahmad->id,
                'items' => [
                    ['code' => 'TMJ-002', 'qty' => 4, 'price' => 14000],
                    ['code' => 'TMJ-003', 'qty' => 3, 'price' => 12000],
                ],
            ],
        ], 'SO-COM');
    }
}
