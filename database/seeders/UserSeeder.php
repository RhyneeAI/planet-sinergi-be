<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'OWNER',
                'phone' => '081572438561',
                'email' => 'owner_gp@gp.com',
                'password' => 'owner_gp',
                'role' => Role::OWNER,
            ],
            [
                'name' => 'SuperAdmin GP',
                'phone' => '081572438561',
                'email' => 'superadmin_gp@gp.com',
                'password' => 'superadmin_gp',
                'role' => Role::SUPERADMIN,
            ],
            [
                'name' => 'Admin GP',
                'phone' => '081976567083',
                'email' => 'admin_gp@gp.com',
                'password' => 'admin_gp',
                'role' => Role::ADMIN,
            ],
            [
                'name' => 'Marketing GP',
                'phone' => '081976567083',
                'email' => 'marketing_gp@gp.com',
                'password' => 'marketing_gp',
                'role' => Role::MARKETING,
            ],
            [
                'name' => 'Mandor GP',
                'phone' => '081976567083',
                'email' => 'mandor_gp@gp.com',
                'password' => 'mandor_gp',
                'role' => Role::MANDOR,
            ],
        ];

        foreach ($users as $user) {
            $attributes = [
                'name' => $user['name'],
                'email' => $user['email'],
                'address' => 'Jl. Demo No. 1, Jakarta',
                'password' => Hash::make($user['password']),
                'role' => $user['role'],
                'company_id' => 1,
                'is_active' => true,
            ];

            $existing = User::where('phone', $user['phone'])->first();

            User::updateOrCreate(
                ['phone' => $user['phone']],
                $existing ? $attributes : array_merge(['uuid' => (string) Str::uuid()], $attributes)
            );
        }
    }
}
