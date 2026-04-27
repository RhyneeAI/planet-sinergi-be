<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Company 1
            ['name' => 'Makanan', 'company_id' => 1],
            ['name' => 'Minuman', 'company_id' => 1],
            ['name' => 'Snack', 'company_id' => 1],
            ['name' => 'Rokok', 'company_id' => 1],
            // Company 2
            ['name' => 'Elektronik', 'company_id' => 2],
            ['name' => 'Aksesoris', 'company_id' => 2],
            ['name' => 'Perlengkapan', 'company_id' => 2],
            // Company 3
            ['name' => 'Pakaian', 'company_id' => 3],
            ['name' => 'Sepatu', 'company_id' => 3],
            ['name' => 'Aksesoris Fashion', 'company_id' => 3],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
