<?php

namespace Database\Seeders;

use App\Models\AbsJabatan;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AbsJabatanSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Super Admin', 'daily_rate' => 0],
            ['name' => 'Owner', 'daily_rate' => 0],
            ['name' => 'Admin', 'daily_rate' => 0],
            ['name' => 'Hrd', 'daily_rate' => 0],
            ['name' => 'Marketing Leader', 'daily_rate' => 0],
            ['name' => 'Marketing', 'daily_rate' => 0],
            ['name' => 'Kasir', 'daily_rate' => 0],
            ['name' => 'Mandor', 'daily_rate' => 0],
            ['name' => 'Karyawan', 'daily_rate' => 0],
        ];

        Company::withoutGlobalScopes()->each(function (Company $company) use ($defaults) {
            foreach ($defaults as $jabatan) {
                AbsJabatan::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $jabatan['name'],
                    ],
                    [
                        'daily_rate' => $jabatan['daily_rate'],
                    ]
                );
            }
        });
    }
}
