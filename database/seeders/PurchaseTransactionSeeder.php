<?php

namespace Database\Seeders;

use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\PurchaseTransaction;
use App\Models\Supplier;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PurchaseTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $companyId = 1;
        
        // Ambil user dan supplier yang ada
        $users = User::where('company_id', $companyId)->pluck('id')->toArray();
        $suppliers = Supplier::where('company_id', $companyId)->pluck('id')->toArray();
        
        if (empty($users) || empty($suppliers)) {
            return; // Skip jika tidak ada user atau supplier
        }

        $transactions = [];
        for ($i = 0; $i < 20; $i++) {
            $discount = $faker->randomFloat(2, 0, 5000);
            $subtotal = $faker->randomFloat(2, 50000, 500000);
            $total = max(0, $subtotal - $discount);
            
            $transactions[] = [
                'ulid'                => (string) Str::ulid(),
                'transaction_code'    => 'PO-' . date('Ymd') . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'transaction_date'    => $faker->dateTimeBetween('-30 days', 'now'),
                'discount'            => $discount,
                'total'               => $total,
                'paid'                => $faker->randomFloat(2, 0, $total),
                'payment_type'        => $faker->randomElement(PaymentType::cases())->value,
                'transaction_status'  => $faker->randomElement(TransactionStatus::cases())->value,
                'supplier_id'         => $faker->randomElement($suppliers),
                'created_by'          => $faker->randomElement($users),
                'company_id'          => $companyId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        // Insert batch 10 data untuk performa
        foreach (array_chunk($transactions, 10) as $chunk) {
            PurchaseTransaction::insert($chunk);
        }
    }
}
