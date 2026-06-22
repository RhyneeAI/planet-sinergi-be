<?php

namespace Database\Seeders;

use App\Models\PosCustomerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosCustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['type' => 'Regular', 'discount' => 0],
            ['type' => 'Member', 'discount' => 10],
        ];

        foreach ($types as $type) {
            PosCustomerType::firstOrCreate(
                ['type' => $type['type'], 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'discount' => $type['discount'],
                    'created_by' => 1,
                ]
            );
        }
    }
}
