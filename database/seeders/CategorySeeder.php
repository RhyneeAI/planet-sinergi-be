<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Company 1
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Makanan',
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Minuman',
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Snack',
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Rokok',
                'company_id' => 1,
            ],
            // Company 2
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Elektronik',
                'company_id' => 2,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Aksesoris',
                'company_id' => 2,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Perlengkapan',
                'company_id' => 2,
            ],
            // Company 3
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Pakaian',
                'company_id' => 3,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Sepatu',
                'company_id' => 3,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Aksesoris Fashion',
                'company_id' => 3,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}