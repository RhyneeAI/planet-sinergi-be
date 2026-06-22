<?php

namespace Database\Seeders;

use App\Models\PosUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Pcs', 'Kg'] as $name) {
            PosUnit::firstOrCreate(
                ['name' => $name, 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'created_by' => 1,
                ]
            );
        }
    }
}
