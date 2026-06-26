<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'uuid'       => fake()->uuid(),
            'name'       => fake()->name(),
            'phone'      => fake()->unique()->numerify('08##########'),
            'email'      => fake()->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            'role'       => Role::OWNER,
            'address'    => fake()->address(),
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

    public function karyawan(): static
    {
        return $this->state(['role' => Role::KARYAWAN]);
    }

    public function mandor(): static
    {
        return $this->state(['role' => Role::MANDOR]);
    }

    public function kepalaMandor(): static
    {
        return $this->state(['role' => Role::KEPALA_MANDOR]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::ADMIN]);
    }

    public function hrd(): static
    {
        return $this->state(['role' => Role::HRD]);
    }

    public function manajerGudang(): static
    {
        return $this->state(['role' => Role::MANAGER_GUDANG]);
    }

    public function marketingLead(): static
    {
        return $this->state(['role' => Role::MARKETING_LEAD]);
    }

    public function marketingTetap(): static
    {
        return $this->state(['role' => Role::MARKETING_TETAP]);
    }

    public function kasir(): static
    {
        return $this->state(['role' => Role::KASIR]);
    }
}