<?php

namespace Database\Seeders\Test;

use App\Enums\Role;
use App\Models\PosCategory;
use App\Models\Company;
use App\Models\PosCustomerType;
use App\Models\PosUnit;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class StagingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::create([
            'uuid'     => Str::uuid(),
            'name'     => 'Toko Sejahtera Staging',
            'address'  => 'Jl. Staging No. 99',
            'code'     => 'TSS-STAGING',
        ]);

        $owner = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner Staging',
            'phone'      => '081234567901',
            'email'      => 'owner_gp_staging@sejahtera.com',
            'password'   => Hash::make('owner_gp_staging'),
            'role'       => Role::OWNER,
            'company_id' => $company->id,
        ]);

        $cashier = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Kasir Sejahtera',
            'phone'      => '081234567902',
            'email'      => 'kasir_gp_staging@sejahtera.com',
            'password'   => Hash::make('kasir_gp_staging'),
            'role'       => Role::MARKETING,
            'company_id' => $company->id,
        ]);

        // ================================
        // Master Data (Optional - uncomment jika perlu)
        // ================================
        // $category = PosCategory::create([
        //     'uuid'       => Str::uuid(),
        //     'name'       => 'Kebutuhan Rumah',
        //     'created_by' => $owner->id,
        //     'company_id' => $company->id,
        // ]);

        // $unit = PosUnit::create([
        //     'uuid'       => Str::uuid(),
        //     'name'       => 'Pcs',
        //     'created_by' => $owner->id,
        //     'company_id' => $company->id,
        // ]);

        // $customerType = PosCustomerType::create([
        //     'uuid'       => Str::uuid(),
        //     'type'       => 'Regular',
        //     'discount'   => 0,
        //     'created_by' => $owner->id,
        //     'company_id' => $company->id,
        // ]);
    }
}