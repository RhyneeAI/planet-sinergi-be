<?php

namespace Database\Seeders;

use App\Models\PosCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Makanan', 'Minuman'] as $name) {
            PosCategory::firstOrCreate(
                ['name' => $name, 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'created_by' => 1,
                ]
            );
        }
    }
}
