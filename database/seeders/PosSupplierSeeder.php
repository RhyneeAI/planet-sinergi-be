<?php

namespace Database\Seeders;

use App\Models\PosSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Supplier Utama', 'phone' => '0211111111'],
            ['name' => 'Supplier Cadangan', 'phone' => '0212222222'],
        ];

        foreach ($suppliers as $supplier) {
            PosSupplier::firstOrCreate(
                ['name' => $supplier['name'], 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'address' => 'Jl. Supplier, Jakarta',
                    'phone' => $supplier['phone'],
                    'created_by' => 1,
                ]
            );
        }
    }
}
