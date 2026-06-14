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
                'phone' => '081122223333',
                'email' => 'admin_gp@gp.com',
                'address' => $faker->address,
                'password' => Hash::make('admin_gp'),
                'role' => Role::ADMIN,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mandor GP 1',
                'phone' => '082233334444',
                'email' => 'mandor_gp_1@gp.com',
                'address' => $faker->address,
                'password' => Hash::make('mandor_gp_1'),
                'role' => Role::MANDOR,
                'company_id' => 1,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mandor GP 2',
                'phone' => '083344445555',
                'email' => 'mandor_gp_2@gp.com',
                'address' => $faker->address,
                'password' => Hash::make('mandor_gp_2'),
                'role' => Role::MANDOR,
                'company_id' => 1,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['phone' => $user['phone']], $user);
        }
    }
}