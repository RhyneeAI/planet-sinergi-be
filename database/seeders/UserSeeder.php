<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\Role;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        // User default untuk setiap role
        $users = [
            // Admin untuk setiap company
            [
                'name' => 'OWNER',
                'username' => 'owner_gp',
                'email' => 'owner_gp@gp.com',
                'password' => Hash::make('owner_gp'),
                'role' => Role::OWNER,
                'company_id' => 1,
            ],
            [
                'name' => 'Admin SA',
                'username' => 'admin_gp',
                'email' => 'admin_gp@gp.com',
                'password' => Hash::make('admin_gp'),
                'role' => Role::ADMIN,
                'company_id' => 2,
            ],
            [
                'name' => 'Marketer BS',
                'username' => 'marketer_bs',
                'email' => 'marketer_bs@gp.com',
                'password' => Hash::make('marketer_bs'),
                'role' => Role::MARKETER,
                'company_id' => 3,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
