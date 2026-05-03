<?php

namespace Database\Seeders;

use App\Enums\StockMutationType;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockMutationSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan ada company, user, dan product
        $company = Company::first() ?? Company::factory()->create();
        $user = User::first() ?? User::factory()->owner()->create(['company_id' => $company->id]);
        
        // Buat produk jika belum ada
        $products = Product::where('company_id', $company->id)->take(3)->get();
        if ($products->isEmpty()) {
            $products = Product::factory(3)->create(['company_id' => $company->id]);
        }

        $mutations = [];
        $startDate = now()->subDays(30);

        foreach ($products as $product) {
            $stock = 0;
            
            // Generate 10-20 mutasi per produk
            for ($i = 0; $i < rand(10, 20); $i++) {
                $date = $startDate->copy()->addDays(rand(0, 30));
                $type = fake()->randomElement([
                    StockMutationType::PURCHASE_IN,
                    StockMutationType::SALES_OUT,
                    StockMutationType::ADJUST_IN,
                    StockMutationType::ADJUST_OUT,
                ]);
                
                $quantity = match ($type) {
                    StockMutationType::PURCHASE_IN, StockMutationType::ADJUST_IN => rand(10, 100),
                    StockMutationType::SALES_OUT, StockMutationType::ADJUST_OUT => rand(1, 50),
                };
                
                $stockBefore = $stock;
                $stockAfter = match ($type) {
                    StockMutationType::PURCHASE_IN, StockMutationType::ADJUST_IN => $stockBefore + $quantity,
                    StockMutationType::SALES_OUT, StockMutationType::ADJUST_OUT => $stockBefore - $quantity,
                    default => $stockBefore,
                };
                
                // Pastikan stock tidak negatif
                if ($stockAfter < 0) {
                    $stockAfter = 0;
                }
                
                $stock = $stockAfter;
                
                $mutations[] = [
                    'ulid' => (string) \Illuminate\Support\Str::ulid(),
                    'type' => $type,
                    'quantity' => $quantity,
                    'stock_before' => max(0, $stockBefore),
                    'stock_after' => $stockAfter,
                    'notes' => fake()->optional()->sentence(),
                    'product_id' => $product->id,
                    'company_id' => $company->id,
                    'reference_id' => null,
                    'created_by' => $user->id,
                    'created_at' => $date,
                ];
            }
            
            // Update stok akhir produk
            $product->update(['stock' => $stock]);
        }
        
        // Insert batch
        foreach (array_chunk($mutations, 50) as $chunk) {
            StockMutation::insert($chunk);
        }
    }
}