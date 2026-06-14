<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SalesRevenueReportSeeder extends Seeder
{
    public function run(): void
    {
        // ================================
        // Company & Users baru
        // ================================
        $company = Company::create([
            'uuid'       => Str::uuid(),
            'name'    => 'Toko Sejahtera',
            'address' => 'Jl. Merdeka No. 99',
            'code'    => 'TSJ-001',
        ]);

        // Gunakan phone yang belum dipakai
        $owner = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner Sejahtera',
            'phone'      => '081234567903', // ← ganti
            'email'      => 'owner@sejahtera.com',
            'password'   => Hash::make('owner_gp3'),
            'role'       => Role::OWNER,
            'company_id' => $company->id,
        ]);

        $cashier = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Kasir Sejahtera',
            'phone'      => '081234567904', // ← ganti
            'email'      => 'kasir@sejahtera.com',
            'password'   => Hash::make('password'),
            'role'       => Role::MARKETING,
            'company_id' => $company->id,
        ]);

        $cashier2 = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Kasir Kuda',
            'phone'      => '081234567905', // ← ganti
            'email'      => 'kasirkuda@sejahtera.com',
            'password'   => Hash::make('password'),
            'role'       => Role::MARKETING,
            'company_id' => $company->id,
        ]);

        // ================================
        // Master Data
        // ================================
        $category = Category::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Kebutuhan Rumah',
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $unit = Unit::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Pcs',
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $customerType = CustomerType::create([
            'uuid'       => Str::uuid(),
            'type'       => 'Regular',
            'discount'   => 0,
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        // ================================
        // Customers
        // ================================
        $customers = collect(['Budi', 'Siti', 'Andi', 'Rini', 'Joko'])->map(fn($name) =>
            Customer::create([
                'uuid'             => Str::uuid(),
                'name'             => $name,
                'customer_type_id' => $customerType->id,
                'created_by'       => $owner->id,
                'company_id'       => $company->id,
            ])
        );

        // ================================
        // Products — 7 produk berbeda
        // ================================
        $productsData = [
            ['name' => 'Sabun Mandi Lifebuoy',   'code' => 'SJ-001', 'base' => 3000,  'sell' => 5000,  'stock' => 500],
            ['name' => 'Shampoo Sunsilk',         'code' => 'SJ-002', 'base' => 10000, 'sell' => 15000, 'stock' => 300],
            ['name' => 'Pasta Gigi Pepsodent',    'code' => 'SJ-003', 'base' => 7000,  'sell' => 10000, 'stock' => 400],
            ['name' => 'Minyak Goreng Bimoli 1L', 'code' => 'SJ-004', 'base' => 14000, 'sell' => 18000, 'stock' => 200],
            ['name' => 'Detergen Rinso 1kg',      'code' => 'SJ-005', 'base' => 9000,  'sell' => 13000, 'stock' => 350],
            ['name' => 'Teh Botol Sosro',         'code' => 'SJ-006', 'base' => 4000,  'sell' => 6000,  'stock' => 600],
            ['name' => 'Indomie Goreng',          'code' => 'SJ-007', 'base' => 2500,  'sell' => 4000,  'stock' => 800],
        ];

        $products = [];
        foreach ($productsData as $pd) {
            $products[$pd['code']] = Product::create([
                'uuid'        => Str::uuid(),
                'name'        => $pd['name'],
                'code'        => $pd['code'],
                'base_price'  => $pd['base'],
                'sales_price' => $pd['sell'],
                'stock'       => $pd['stock'],
                'is_active'   => true,
                'category_id' => $category->id,
                'unit_id'     => $unit->id,
                'created_by'  => $owner->id,
                'company_id'  => $company->id,
            ]);
        }

        // ================================
        // Transactions — 10 transaksi
        // ================================
        $salesData = [
            [
                'date'       => '2026-01-05',
                'customer'   => $customers[0],
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'created_by' => $cashier->id,
                'items'      => [
                    ['code' => 'SJ-007', 'qty' => 10, 'price' => 4000],
                    ['code' => 'SJ-006', 'qty' => 5,  'price' => 6000],
                    ['code' => 'SJ-001', 'qty' => 3,  'price' => 5000],
                ],
            ],
            [
                'date'       => '2026-01-12',
                'customer'   => $customers[1],
                'payment'    => PaymentType::TRANSFER,
                'discount'   => 5000,
                'created_by' => $cashier2->id,
                'items'      => [
                    ['code' => 'SJ-004', 'qty' => 3,  'price' => 18000],
                    ['code' => 'SJ-007', 'qty' => 15, 'price' => 4000],
                    ['code' => 'SJ-003', 'qty' => 4,  'price' => 10000],
                ],
            ],
            [
                'date'       => '2026-01-20',
                'customer'   => null,
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'created_by' => $cashier->id,
                'items'      => [
                    ['code' => 'SJ-007', 'qty' => 20, 'price' => 4000],
                    ['code' => 'SJ-001', 'qty' => 6,  'price' => 5000],
                ],
            ],
            [
                'date'       => '2026-02-03',
                'customer'   => $customers[2],
                'payment'    => PaymentType::QRIS,
                'discount'   => 10000,
                'created_by' => $cashier2->id,
                'items'      => [
                    ['code' => 'SJ-002', 'qty' => 4,  'price' => 15000],
                    ['code' => 'SJ-005', 'qty' => 5,  'price' => 13000],
                    ['code' => 'SJ-007', 'qty' => 8,  'price' => 4000],
                ],
            ],
            [
                'date'       => '2026-02-14',
                'customer'   => $customers[3],
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'created_by' => $cashier->id,
                'items'      => [
                    ['code' => 'SJ-006', 'qty' => 10, 'price' => 6000],
                    ['code' => 'SJ-003', 'qty' => 6,  'price' => 10000],
                    ['code' => 'SJ-007', 'qty' => 12, 'price' => 4000],
                ],
            ],
            [
                'date'       => '2026-02-28',
                'customer'   => $customers[4],
                'payment'    => PaymentType::TRANSFER,
                'discount'   => 0,
                'created_by' => $cashier2->id,
                'items'      => [
                    ['code' => 'SJ-004', 'qty' => 5,  'price' => 18000],
                    ['code' => 'SJ-005', 'qty' => 8,  'price' => 13000],
                    ['code' => 'SJ-001', 'qty' => 10, 'price' => 5000],
                ],
            ],
            [
                'date'       => '2026-03-07',
                'customer'   => $customers[0],
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'created_by' => $cashier->id,
                'items'      => [
                    ['code' => 'SJ-007', 'qty' => 25, 'price' => 4000],
                    ['code' => 'SJ-006', 'qty' => 8,  'price' => 6000],
                ],
            ],
            [
                'date'       => '2026-03-15',
                'customer'   => $customers[1],
                'payment'    => PaymentType::QRIS,
                'discount'   => 15000,
                'created_by' => $cashier2->id,
                'items'      => [
                    ['code' => 'SJ-002', 'qty' => 6,  'price' => 15000],
                    ['code' => 'SJ-003', 'qty' => 8,  'price' => 10000],
                    ['code' => 'SJ-004', 'qty' => 4,  'price' => 18000],
                ],
            ],
            [
                'date'       => '2026-03-22',
                'customer'   => null,
                'payment'    => PaymentType::CASH,
                'discount'   => 0,
                'created_by' => $cashier->id,
                'items'      => [
                    ['code' => 'SJ-007', 'qty' => 30, 'price' => 4000],
                    ['code' => 'SJ-001', 'qty' => 12, 'price' => 5000],
                    ['code' => 'SJ-005', 'qty' => 6,  'price' => 13000],
                ],
            ],
            [
                'date'       => '2026-04-01',
                'customer'   => $customers[2],
                'payment'    => PaymentType::TRANSFER,
                'discount'   => 5000,
                'created_by' => $cashier2->id,
                'items'      => [
                    ['code' => 'SJ-006', 'qty' => 15, 'price' => 6000],
                    ['code' => 'SJ-002', 'qty' => 5,  'price' => 15000],
                    ['code' => 'SJ-007', 'qty' => 20, 'price' => 4000],
                ],
            ],
        ];

        foreach ($salesData as $index => $sale) {
            $total = collect($sale['items'])->sum(fn($i) => $i['qty'] * $i['price']);
            $totalAfterDiscount = $total - $sale['discount'];

            $trxCode = 'SO-REV-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);

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
                'created_by'         => $sale['created_by'],
                'company_id'         => $company->id,
            ]);

            foreach ($sale['items'] as $item) {
                $product  = $products[$item['code']];
                $subtotal = $item['qty'] * $item['price'];

                SalesDetail::create([
                    'ulid'            => Str::ulid(),
                    'sale_id'         => $transaction->id,
                    'product_id'      => $product->id,
                    'quantity'        => $item['qty'],
                    'sell_price'      => $item['price'],
                    'marketing_price' => $product->sales_price, // ← tambahkan ini
                    'discount'        => 0,
                    'subtotal'        => $subtotal,
                    'company_id'      => $company->id,
                ]);

                $product->decrement('stock', $item['qty']);
            }
        }

        $this->command->info('SalesRevenueReportSeeder selesai.');
        $this->command->info('Company: Toko Sejahtera');
        $this->command->info('Owner phone: 081234567893 / password: owner_gp3');
        $this->command->info('Period test: 2026-01-01 s/d 2026-04-30');
        $this->command->info('');
        $this->command->info('Ekspektasi Top Produk (Indomie = ' . (10+15+20+8+12+25+30+20) . ' qty):');

        $expected = [
            'SJ-007 Indomie Goreng'          => 10+15+20+8+12+25+30+20,
            'SJ-001 Sabun Mandi Lifebuoy'    => 3+6+10+12,
            'SJ-006 Teh Botol Sosro'         => 5+10+8+15,
            'SJ-005 Detergen Rinso'          => 5+8+6,
            'SJ-003 Pasta Gigi Pepsodent'    => 4+6+8,
            'SJ-004 Minyak Goreng Bimoli'    => 3+5+4,
            'SJ-002 Shampoo Sunsilk'         => 4+6+5,
        ];

        foreach ($expected as $product => $qty) {
            $this->command->info("  {$product}: {$qty} qty");
        }
    }
}