<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\Role;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Str;

class OperationalUserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // User default untuk setiap role
        $users = [
            // Admin untuk setiap company
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin GP',
                'username' => 'admin_gp',
                'email' => 'admin_gp@gp.com',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('admin_gp'),
                'role' => Role::ADMIN,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mandor GP 1',
                'username' => 'mandor_gp_1',
                'email' => 'mandor_gp_1@gp.com',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('mandor_gp_1'),
                'role' => Role::MANDOR,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mandor GP 2',
                'username' => 'mandor_gp_2',
                'email' => 'mandor_gp_2@gp.com',
                'address' => $faker->address,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('mandor_gp_2'),
                'role' => Role::MANDOR,
                'company_id' => 1,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }
    }
}
