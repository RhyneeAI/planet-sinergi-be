<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CustomConfiguration;
use Illuminate\Database\Seeder;

class CustomConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'overtime_hourly_rate',
                'value' => '25000',
                'description' => 'Tarif lembur per jam (global)',
            ],
        ];

        Company::withoutGlobalScopes()->each(function (Company $company) use ($defaults) {
            foreach ($defaults as $config) {
                CustomConfiguration::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'key' => $config['key'],
                    ],
                    [
                        'value' => $config['value'],
                        'description' => $config['description'],
                    ]
                );
            }
        });
    }
}
