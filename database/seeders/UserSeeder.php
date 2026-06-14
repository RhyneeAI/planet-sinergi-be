<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\Role;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        // User default untuk setiap role
        $users = [
            // Admin untuk setiap company
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'OWNER',
                'phone' => '081234567891',
                'email' => 'owner_gp@gp.com',
                'address' => $faker->address,
                'password' => Hash::make('owner_gp'),
                'role' => Role::OWNER,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'SuperAdmin GP',
                'phone' => '081234567892',
                'email' => 'superadmin_gp@gp.com',
                'address' => $faker->address,
                'password' => Hash::make('superadmin_gp'),
                'role' => Role::SUPERADMIN,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Marketing GP',
                'phone' => '081234567893',
                'address' => $faker->address,
                'email' => 'marketing_gp@gp.com',
                'password' => Hash::make('marketing_gp'),
                'role' => Role::MARKETING,
                'company_id' => 1,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}