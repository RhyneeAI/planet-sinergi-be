<?php

namespace Database\Seeders;

use App\Enums\PaymentType;
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
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketingCommissionReportSeeder extends Seeder
{
    public function run(): void
    {
        // ================================
        // Ambil atau buat Company
        // ================================
        $company = Company::updateOrCreate(
            ['code' => 'TMJ-001'],
            [
                'uuid'       => (string) Str::uuid(),
                'name'       => 'Toko Maju Jaya',
                'code'       => 'TMJ-001',
            ]
        );

        // ================================
        // Ambil atau buat Owner
        // ================================
        $owner = User::updateOrCreate(
            ['phone' => '081234567890'],
            [
                'uuid'       => (string) Str::uuid(),
                'name'       => 'Owner',
                'phone'      => '081234567890',
                'email'      => 'owner@example.com',
                'password'   => Hash::make('owner_gp2'),
                'role'       => Role::OWNER,
                'company_id' => $company->id,
            ]
        );

        // ================================
        // Buat Marketing Users (dengan phone)
        // ================================
        $abdillah = User::updateOrCreate(
            ['phone' => '081234567891'],
            [
                'uuid'       => Str::uuid(),
                'name'       => 'Abdillah',
                'phone'      => '081234567891',
                'email'      => 'abdillah@example.com',
                'password'   => Hash::make('password'),
                'role'       => Role::MARKETING,
                'company_id' => $company->id,
            ]
        );

        $ahmad = User::updateOrCreate(
            ['phone' => '081234567892'],
            [
                'uuid'       => Str::uuid(),
                'name'       => 'Ahmad',
                'phone'      => '081234567892',
                'email'      => 'ahmad@example.com',
                'password'   => Hash::make('password'),
                'role'       => Role::MARKETING,
                'company_id' => $company->id,
            ]
        );

        // ================================
        // Buat Master Data
        // ================================
        $category = Category::firstOrCreate(
            ['name' => 'Produk Umum', 'company_id' => $company->id],
            [
                'uuid'       => Str::uuid(),
                'name'       => 'Produk Umum',
                'created_by' => $owner->id,
                'company_id' => $company->id,
            ]
        );

        $unit = Unit::firstOrCreate(
            ['name' => 'Pcs', 'company_id' => $company->id],
            [
                'uuid'       => Str::uuid(),
                'name'       => 'Pcs',
                'created_by' => $owner->id,
                'company_id' => $company->id,
            ]
        );

        $customerType = CustomerType::firstOrCreate(
            ['type' => 'Regular', 'company_id' => $company->id],
            [
                'uuid'       => Str::uuid(),
                'type'       => 'Regular',
                'discount'   => 0,
                'created_by' => $owner->id,
                'company_id' => $company->id,
            ]
        );

        // ================================
        // Buat Customers
        // ================================
        $customers = [];
        $customerNames = ['Andi', 'Budi', 'Siti', 'Keysha', 'Zhee', 'Abdi'];

        foreach ($customerNames as $name) {
            $customers[$name] = Customer::firstOrCreate(
                ['name' => $name, 'company_id' => $company->id],
                [
                    'uuid'             => Str::uuid(),
                    'name'             => $name,
                    'customer_type_id' => $customerType->id,
                    'created_by'       => $owner->id,
                    'company_id'       => $company->id,
                ]
            );
        }

        // ================================
        // Buat Products (dengan marketing_price)
        // ================================
        $productsData = [
            [
                'name'             => 'Lifebuoy Sabun',
                'code'             => 'PRD-001',
                'base_price'       => 5000,
                'sales_price'      => 8000,
                'marketing_price'  => 6500,
                'stock'            => 500,
            ],
            [
                'name'             => 'Shampoo Clear',
                'code'             => 'PRD-002',
                'base_price'       => 12000,
                'sales_price'      => 18000,
                'marketing_price'  => 15000,
                'stock'            => 300,
            ],
            [
                'name'             => 'Pasta Gigi Pepsodent',
                'code'             => 'PRD-003',
                'base_price'       => 8000,
                'sales_price'      => 12000,
                'marketing_price'  => 9500,
                'stock'            => 400,
            ],
            [
                'name'             => 'Minyak Goreng Bimoli',
                'code'             => 'PRD-004',
                'base_price'       => 15000,
                'sales_price'      => 20000,
                'marketing_price'  => 17000,
                'stock'            => 200,
            ],
            [
                'name'             => 'Detergen Rinso',
                'code'             => 'PRD-005',
                'base_price'       => 10000,
                'sales_price'      => 14000,
                'marketing_price'  => 12000,
                'stock'            => 350,
            ],
        ];

        $products = [];
        foreach ($productsData as $pd) {
            $products[$pd['code']] = Product::updateOrCreate(
                ['code' => $pd['code'], 'company_id' => $company->id],
                [
                    'uuid'            => Str::uuid(),
                    'name'            => $pd['name'],
                    'code'            => $pd['code'],
                    'base_price'      => $pd['base_price'],
                    'sales_price'     => $pd['sales_price'],
                    'marketing_price' => $pd['marketing_price'],  // ← PASTIKAN INI ADA
                    'stock'           => $pd['stock'],
                    'is_active'       => true,
                    'category_id'     => $category->id,
                    'unit_id'         => $unit->id,
                    'created_by'      => $owner->id,
                    'company_id'      => $company->id,
                ]
            );
        }

        // ================================
        // Assign Marketing Products
        // ================================
        foreach ([$abdillah, $ahmad] as $marketing) {
            foreach ($products as $code => $product) {
                MarketingProduct::firstOrCreate(
                    [
                        'product_id'   => $product->id,
                        'marketing_id' => $marketing->id,
                        'company_id'   => $company->id,
                    ],
                    [
                        'uuid'       => Str::uuid(),
                        'created_by' => $owner->id,
                    ]
                );
            }
        }

        // ================================
        // Buat Sales Transactions
        // ================================
        $salesData = [
            // Abdillah transactions
            [
                'marketing'  => $abdillah,
                'date'       => '2026-01-15',
                'customer'   => $customers['Andi'],
                'payment'    => PaymentType::CASH,
                'discount'   => 2000,
                'items'      => [
                    ['product' => 'PRD-001', 'qty' => 5, 'price' => 6500],
                    ['product' => 'PRD-003', 'qty' => 3, 'price' => 10000],
                ],
            ],
            [
                'marketing'  => $abdillah,
                'date'       => '2026-02-10',
                'customer'   => $customers['Budi'],
                'payment'    => PaymentType::TRANSFER,
                'discount'   => 0,
                'items'      => [
                    ['product' => 'PRD-002', 'qty' => 4, 'price' => 15000],
                    ['product' => 'PRD-004', 'qty' => 2, 'price' => 18000],
                ],
            ],
            [
                'marketing'  => $abdillah,
                'date'       => '2026-03-05',
                'customer'   => null,
                'payment'    => PaymentType::QRIS,
                'discount'   => 5000,
                'items'      => [
                    ['product' => 'PRD-005', 'qty' => 10, 'price' => 12500],
                    ['product' => 'PRD-001', 'qty' => 8, 'price' => 6500],
                ],
            ],
            [
                'marketing'  => $abdillah,
                'date'       => '2026-04-20',
                'customer'   => $customers['Keysha'],
                'payment'    => PaymentType::CASH,
                'discount'   => 10000,
                'items'      => [
                    ['product' => 'PRD-002', 'qty' => 6, 'price' => 15000],
                    ['product' => 'PRD-003', 'qty' => 5, 'price' => 10000],
                    ['product' => 'PRD-004', 'qty' => 3, 'price' => 18000],
                ],
            ],
            // Ahmad transactions
            [
                'marketing'  => $ahmad,
                'date'       => '2026-01-08',
                'customer'   => $customers['Siti'],
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'items'      => [
                    ['product' => 'PRD-001', 'qty' => 10, 'price' => 6000],
                    ['product' => 'PRD-002', 'qty' => 3, 'price' => 14000],
                ],
            ],
            [
                'marketing'  => $ahmad,
                'date'       => '2026-02-14',
                'customer'   => $customers['Zhee'],
                'payment'    => PaymentType::TRANSFER,
                'discount'   => 3000,
                'items'      => [
                    ['product' => 'PRD-003', 'qty' => 7, 'price' => 9500],
                    ['product' => 'PRD-005', 'qty' => 4, 'price' => 12000],
                ],
            ],
            [
                'marketing'  => $ahmad,
                'date'       => '2026-03-22',
                'customer'   => $customers['Abdi'],
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'items'      => [
                    ['product' => 'PRD-004', 'qty' => 5, 'price' => 17000],
                    ['product' => 'PRD-001', 'qty' => 15, 'price' => 6000],
                ],
            ],
            [
                'marketing'  => $ahmad,
                'date'       => '2026-04-05',
                'customer'   => null,
                'payment'    => PaymentType::QRIS,
                'discount'   => 8000,
                'items'      => [
                    ['product' => 'PRD-002', 'qty' => 8, 'price' => 14000],
                    ['product' => 'PRD-003', 'qty' => 6, 'price' => 9500],
                    ['product' => 'PRD-005', 'qty' => 5, 'price' => 12000],
                ],
            ],
        ];

        foreach ($salesData as $index => $sale) {
            $total = collect($sale['items'])->sum(fn($i) => $i['qty'] * $i['price']);
            $totalAfterDiscount = $total - $sale['discount'];

            $trxCode = 'SO-SEED-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);

            if (SalesTransaction::where('transaction_code', $trxCode)->exists()) {
                continue;
            }

            $transaction = SalesTransaction::create([
                'ulid'               => Str::ulid(),
                'transaction_code'   => $trxCode,
                'transaction_date'   => $sale['date'],
                'discount'           => $sale['discount'],
                'total'              => $totalAfterDiscount,
                'paid'               => $totalAfterDiscount,
                'payment_type'       => $sale['payment'],
                'transaction_status' => TransactionStatus::PAID,
                'customer_id'        => $sale['customer']?->id,
                'created_by'         => $sale['marketing']->id,
                'company_id'         => $company->id,
            ]);

            foreach ($sale['items'] as $item) {
                $product  = $products[$item['product']];
                $subtotal = $item['qty'] * $item['price'];

                SalesDetail::create([
                    'ulid'            => Str::ulid(),
                    'sale_id'         => $transaction->id,
                    'product_id'      => $product->id,
                    'quantity'        => $item['qty'],
                    'sell_price'      => $item['price'],
                    'marketing_price' => $product->marketing_price, // ← sekarang sudah ada nilainya
                    'discount'        => 0,
                    'subtotal'        => $subtotal,
                    'company_id'      => $company->id,
                ]);

                $product->decrement('stock', $item['qty']);
            }
        }

        $this->command->info('MarketingCommissionReportSeeder selesai.');
        $this->command->info('Marketing: Abdillah (' . $abdillah->uuid . ')');
        $this->command->info('Marketing: Ahmad (' . $ahmad->uuid . ')');
        $this->command->info('Period test: 2026-01-01 s/d 2026-04-30');
    }
}