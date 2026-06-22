<?php

namespace Database\Seeders;

use App\Models\PosCustomer;
use App\Models\PosCustomerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PosCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customerTypeId = PosCustomerType::where('company_id', 1)->value('id');

        foreach (['Andi', 'Budi', 'Siti'] as $index => $name) {
            PosCustomer::firstOrCreate(
                ['name' => $name, 'company_id' => 1],
                [
                    'uuid' => (string) Str::uuid(),
                    'phone' => '08188000000' . $index,
                    'address' => 'Jl. Pelanggan, Jakarta',
                    'customer_type_id' => $customerTypeId,
                    'created_by' => 1,
                ]
            );
        }
    }
}
