<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $unitsData = [
            ['name' => 'Pcs', 'company_id' => 1],
            ['name' => 'Kg', 'company_id' => 1],
            ['name' => 'Liter', 'company_id' => 1],
            ['name' => 'Dus', 'company_id' => 1],
            ['name' => 'Pack', 'company_id' => 1],
            ['name' => 'Pcs', 'company_id' => 2],
            ['name' => 'Kg', 'company_id' => 2],
            ['name' => 'Dus', 'company_id' => 2],
            ['name' => 'Pcs', 'company_id' => 3],
            ['name' => 'Kg', 'company_id' => 3],
        ];

        $units = [];
        foreach ($unitsData as $data) {
            $units[] = [
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'company_id' => $data['company_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Unit::insert($units);
    }
}