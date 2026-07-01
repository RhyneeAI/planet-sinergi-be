<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $jabatans = [
            'Super Admin' => 0,
            'Owner' => 0,
            'Admin' => 0,
            'Hrd' => 0,
            'Marketing Leader' => 0,
            'Marketing' => 0,
            'Kasir' => 0,
            'Mandor' => 0,
            'Karyawan' => 0,
        ];

        Company::withoutGlobalScopes()->chunk(50, function ($companies) use ($jabatans) {
            foreach ($companies as $company) {
                foreach ($jabatans as $name => $dailyRate) {
                    Position::firstOrCreate(
                        ['company_id' => $company->id, 'name' => $name],
                        ['daily_rate' => $dailyRate]
                    );
                }
            }
        });
    }
}
