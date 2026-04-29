<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['type' => 'Member', 'discount' => 10, 'company_id' => 1],
            ['type' => 'VIP', 'discount' => 20, 'company_id' => 1],
            ['type' => 'Regular', 'discount' => 0, 'company_id' => 1],
            ['type' => 'Member', 'discount' => 5, 'company_id' => 2],
            ['type' => 'VIP', 'discount' => 15, 'company_id' => 2],
            ['type' => 'Member', 'discount' => 10, 'company_id' => 3],
        ];

        foreach ($types as $discount) {
            CustomerType::create([
                'uuid' => (string) Str::uuid(),
                'type' => $discount['type'],
                'discount' => $discount['discount'],
                'company_id' => $discount['company_id'],
            ]);
        }
    }
}