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
                'username' => 'owner_gp',
                'email' => 'owner_gp@gp.com',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('owner_gp'),
                'role' => Role::OWNER,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'SuperAdmin GP',
                'username' => 'superadmin_gp',
                'email' => 'superadmin_gp@gp.com',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('superadmin_gp'),
                'role' => Role::SUPERADMIN,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Marketing GP',
                'username' => 'marketing_gp',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
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
