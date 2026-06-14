<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'uuid'       => fake()->uuid(),
            'name'       => fake()->name(),
            'username'   => fake()->unique()->userName(),
            'email'      => fake()->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            'role'       => Role::MARKETING, // ← default cashier, bukan random
            'address'    => fake()->address(),
            'phone'      => fake()->phoneNumber(),
            'company_id' => Company::factory(),
        ];
    }

    // State methods untuk role tertentu
    public function owner(): static
    {
        return $this->state(['role' => Role::OWNER]);
    }

    public function superAdmin(): static  // ← tambahkan
    {
        return $this->state(['role' => Role::SUPERADMIN]);
    }

    public function marketing(): static
    {
        return $this->state(['role' => Role::MARKETING]);
    }

    public function mandor(): static
    {
        return $this->state(['role' => Role::MANDOR]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::ADMIN]);
    }
}