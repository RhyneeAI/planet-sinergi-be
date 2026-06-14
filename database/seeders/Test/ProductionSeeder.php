<?php

namespace Database\Seeders\Test;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomerType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ================================
        // Company 1 — Gudang Planet (Production)
        // ================================
        $gp = Company::create([
            'uuid'       => Str::uuid(),
            'name'    => 'Gudang Planet',
            'address' => '-',
            'code'    => 'GPL',
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner Gudang Planet',
            'phone'      => '081234567801',
            'email'      => 'owner@gudangplanet.com',
            'password'   => Hash::make('gp_owner'),
            'role'       => Role::OWNER,
            'company_id' => $gp->id,
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Marketing Gudang Planet',
            'phone'      => '081234567802',
            'email'      => 'marketing@gudangplanet.com',
            'password'   => Hash::make('gp_marketing'),
            'role'       => Role::MARKETING,
            'company_id' => $gp->id,
        ]);

        $this->seedMasterData($gp->id, 1); // owner id = 1

        // ================================
        // Company 2 — Gudang Planet 2 (Production 2)
        // ================================
        $gp2 = Company::create([
            'uuid'       => Str::uuid(),
            'name'    => 'Gudang Planet 2',
            'address' => '-',
            'code'    => 'GPL2',
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner Gudang Planet 2',
            'phone'      => '081234567803',
            'email'      => 'owner@gudangplanet2.com',
            'password'   => Hash::make('gp2_owner'),
            'role'       => Role::OWNER,
            'company_id' => $gp2->id,
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Marketing Gudang Planet 2',
            'phone'      => '081234567804',
            'email'      => 'marketing@gudangplanet2.com',
            'password'   => Hash::make('gp2_marketing'),
            'role'       => Role::MARKETING,
            'company_id' => $gp2->id,
        ]);

        $this->seedMasterData($gp2->id, 3); // owner id = 3

        // ================================
        // Company 3 — Gudang Planet Test (Internal)
        // ================================
        $gpTest = Company::create([
            'uuid'       => Str::uuid(),
            'name'    => 'Gudang Planet Test',
            'address' => '-',
            'code'    => 'GPLTEST',
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'SuperAdmin GP Test',
            'phone'      => '081234567805',
            'email'      => 'superadmin@gudangplanet.com',
            'password'   => Hash::make('gp_superadmin'),
            'role'       => Role::SUPERADMIN,
            'company_id' => $gpTest->id,
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Owner GP Test',
            'phone'      => '081234567806',
            'email'      => 'owner@gudangplanettest.com',
            'password'   => Hash::make('gptest_owner'),
            'role'       => Role::OWNER,
            'company_id' => $gpTest->id,
        ]);

        User::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Marketing GP Test',
            'phone'      => '081234567807',
            'email'      => 'marketing@gudangplanettest.com',
            'password'   => Hash::make('gptest_marketing'),
            'role'       => Role::MARKETING,
            'company_id' => $gpTest->id,
        ]);

        $this->seedMasterData($gpTest->id, 5); // owner id = 5
    }

    // ================================
    // Master Data per Company
    // ================================
    private function seedMasterData(int $companyId, int $ownerId): void
    {
        Category::create([
            'uuid'       => Str::uuid(),
            'name'       => 'T-Shirt',
            'created_by' => $ownerId,
            'company_id' => $companyId,
        ]);

        Unit::create([
            'uuid'       => Str::uuid(),
            'name'       => 'Pcs',
            'created_by' => $ownerId,
            'company_id' => $companyId,
        ]);

        $customerTypes = [
            ['type' => 'Regular', 'discount' => 0],
            ['type' => 'Family',  'discount' => 2],
            ['type' => 'Member',  'discount' => 5],
            ['type' => 'VIP',     'discount' => 10],
        ];

        foreach ($customerTypes as $ct) {
            CustomerType::create([
                'uuid'       => Str::uuid(),
                'type'       => $ct['type'],
                'discount'   => $ct['discount'],
                'created_by' => $ownerId,
                'company_id' => $companyId,
            ]);
        }
    }
}