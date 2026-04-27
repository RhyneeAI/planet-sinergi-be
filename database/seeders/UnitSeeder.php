<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
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

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}
