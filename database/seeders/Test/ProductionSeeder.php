<?php

namespace Database\Seeders\Test;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomerType;
use App\Models\Unit;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::create([
            'name'    => 'Gudang Planet',
            'address' => '-',
            'code'    => 'GPL',
        ]);

        $owner = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner',
            'username'   => 'ownergp',
            'email'      => 'owner_gp@gmail.com',
            'password'   => Hash::make('ownergp2026'),
            'role'       => Role::OWNER,
            'company_id' => $company->id,
        ]);

        $cashier = User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Kasir Sejahtera',
            'username'   => 'marketinggp',
            'email'      => 'marketing_gp@sejahtera.com',
            'password'   => Hash::make('marketinggp2026'),
            'role'       => Role::MARKETING,
            'company_id' => $company->id,
        ]);

        // ================================
        // Master Data
        // ================================
        $category = Category::create([
            'uuid'       => Str::uuid(),
            'name'       => 'T-Shirt',
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $unit = Unit::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Pcs',
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $customerType = CustomerType::create([
            'uuid'       => Str::uuid(),
            'type'       => 'Regular',
            'discount'   => 0,
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $customerType = CustomerType::create([
            'uuid'       => Str::uuid(),
            'type'       => 'FAMILY',
            'discount'   => 2,
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $customerType = CustomerType::create([
            'uuid'       => Str::uuid(),
            'type'       => 'MEMBER',
            'discount'   => 5,
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);

        $customerType = CustomerType::create([
            'uuid'       => Str::uuid(),
            'type'       => 'VIP',
            'discount'   => 10,
            'created_by' => $owner->id,
            'company_id' => $company->id,
        ]);
    }
}
