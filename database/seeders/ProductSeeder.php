<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $products = [];

        for ($i = 0; $i < 100; $i++) {
            $companyId = 1;
            
            // Dapatkan category_id, unit_id, supplier_id yang sesuai dengan company
            $categoryIds = Category::where('company_id', $companyId)->pluck('id')->toArray();
            $unitIds = Unit::where('company_id', $companyId)->pluck('id')->toArray();
            $supplierIds = Supplier::where('company_id', $companyId)->pluck('id')->toArray();
            
            $basePrice = $faker->numberBetween(5000, 500000);
            $salesPrice = $basePrice * $faker->randomFloat(2, 1.1, 1.5);
            
            $products[] = [
                'uuid' => (string) Str::uuid(), 
                'name' => $faker->words(3, true),
                'code' => 'PRD' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'base_price' => $basePrice,
                'sales_price' => round($salesPrice, -2), // Pembulatan ke ratusan
                'last_purchase_price' => $basePrice,
                'stock' => $faker->numberBetween(0, 500),
                'min_stock' => $faker->numberBetween(5, 50),
                'description' => $faker->sentence,
                'is_active' => $faker->boolean(90),
                'category_id' => $categoryIds ? $faker->randomElement($categoryIds) : null,
                'unit_id' => $unitIds ? $faker->randomElement($unitIds) : null,
                'supplier_id' => $supplierIds ? $faker->randomElement($supplierIds) : null,
                'user_id' => 1,   
                'company_id' => $companyId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert batch 100 data
        foreach (array_chunk($products, 20) as $chunk) {
            Product::insert($chunk);
        }
    }
}
