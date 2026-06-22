<?php

namespace Database\Seeders;

use App\Models\PosCategory;
use App\Models\PosProduct;
use App\Models\PosUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryId = PosCategory::where('company_id', 1)->value('id');
        $unitId = PosUnit::where('company_id', 1)->value('id');

        $products = [
            ['name' => 'Indomie Goreng', 'code' => 'PRD-001', 'base' => 2500, 'sell' => 4000, 'marketing' => 3500, 'stock' => 200],
            ['name' => 'Teh Botol Sosro', 'code' => 'PRD-002', 'base' => 4000, 'sell' => 6000, 'marketing' => 5000, 'stock' => 150],
            ['name' => 'Sabun Lifebuoy', 'code' => 'PRD-003', 'base' => 5000, 'sell' => 8000, 'marketing' => 6500, 'stock' => 100],
            ['name' => 'Minyak Bimoli 1L', 'code' => 'PRD-004', 'base' => 14000, 'sell' => 18000, 'marketing' => 16000, 'stock' => 80],
            ['name' => 'Detergen Rinso', 'code' => 'PRD-005', 'base' => 9000, 'sell' => 13000, 'marketing' => 11000, 'stock' => 120],
        ];

        foreach ($products as $product) {
            PosProduct::updateOrCreate(
                ['code' => $product['code'], 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $product['name'],
                    'base_price' => $product['base'],
                    'sales_price' => $product['sell'],
                    'marketing_price' => $product['marketing'],
                    'last_purchase_price' => $product['base'],
                    'stock' => $product['stock'],
                    'min_stock' => 10,
                    'is_active' => true,
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'created_by' => 1,
                ]
            );
        }
    }
}
