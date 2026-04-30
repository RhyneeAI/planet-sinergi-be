<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Str;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'PT Maju Jaya',
                'address' => 'Jl. Sudirman No. 123, Jakarta',
                'code' => 'MJ001',
            ],
        ];

        foreach ($companies as $company) {
            Company::create($company);
        }
    }
}
