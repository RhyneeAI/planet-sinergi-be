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
                'password' => Hash::make('owner_gp'),
                'role' => Role::OWNER,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin SA',
                'username' => 'admin_gp',
                'email' => 'admin_gp@gp.com',
                'password' => Hash::make('admin_gp'),
                'role' => Role::ADMIN,
                'company_id' => 2,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Marketing GP',
                'username' => 'marketing_gp',
                'email' => 'marketing_gp@gp.com',
                'password' => Hash::make('marketing_gp'),
                'role' => Role::MARKETING,
                'company_id' => 3,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
