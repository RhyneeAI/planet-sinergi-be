<?php

namespace Database\Seeders\Test;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '081234567889'],
            [
                'uuid'       => (string) Str::uuid(),
                'name'       => 'Superadmin',
                'email'      => 'superadmin@gudangplanet.com',
                'address'    => 'Jl. Raya No. 1, Jakarta',
                'password'   => Hash::make('superadmin123'),
                'role'       => Role::SUPERADMIN,
                'company_id' => null,
                'is_active'  => true,
            ]
        );

        // =============================================
        // COMPANY 1 — UAT / Test
        // =============================================
        $testCompany = Company::firstOrCreate(
            ['code' => 'GP001'],
            [
                'uuid'    => (string) Str::uuid(),
                'name'    => config('app.name') . ' (Test)',
                'address' => 'Jl. Raya No. 1, Jakarta',
            ]
        );

        $testUsers = [
            ['name' => 'Owner Test', 'phone' => '081234567890', 'role' => Role::OWNER],
            ['name' => 'Admin Test', 'phone' => '081234567891', 'role' => Role::ADMIN],
        ];

        foreach ($testUsers as $user) {
            User::updateOrCreate(
                ['phone' => $user['phone']],
                [
                    'uuid'       => (string) Str::uuid(),
                    'name'       => $user['name'],
                    'email'      => strtolower(str_replace(' ', '', $user['name'])) . '@gudangplanet.com',
                    'address'    => 'Jl. Raya No. 1, Jakarta',
                    'password'   => Hash::make('password'),
                    'role'       => $user['role'],
                    'company_id' => $testCompany->id,
                    'is_active'  => true,
                ]
            );
        }

        // =============================================
        // COMPANY 2 — Go-Live (Production)
        // =============================================
        $liveCompany = Company::firstOrCreate(
            ['code' => 'GP002'],
            [
                'uuid'    => (string) Str::uuid(),
                'name'    => config('app.name'),
                'address' => 'Jl. Raya No. 1, Jakarta',
            ]
        );

        $liveUsers = [
            ['name' => 'Owner', 'phone' => '081234567892', 'role' => Role::OWNER],
            ['name' => 'Admin', 'phone' => '081234567893', 'role' => Role::ADMIN],
        ];

        foreach ($liveUsers as $user) {
            User::updateOrCreate(
                ['phone' => $user['phone']],
                [
                    'uuid'       => (string) Str::uuid(),
                    'name'       => $user['name'],
                    'email'      => strtolower($user['name']) . '@gudangplanet.com',
                    'address'    => 'Jl. Raya No. 1, Jakarta',
                    'password'   => Hash::make('password'),
                    'role'       => $user['role'],
                    'company_id' => $liveCompany->id,
                    'is_active'  => true,
                ]
            );
        }
    }
}
