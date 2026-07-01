<?php

namespace App\Services\Absence;

use App\Enums\Role;
use App\Models\AbsEmployeeProfile;
use App\Models\AbsShift;
use App\Models\Position;
use App\Models\SubCompany;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbsEmployeeProfileService
{
    public function syncForUser(User $user, array $attributes = []): AbsEmployeeProfile
    {
        $existing = AbsEmployeeProfile::where('user_id', $user->id)->first();

        $payload = [
            'abs_jabatan_id' => array_key_exists('abs_jabatan_id', $attributes)
                ? $attributes['abs_jabatan_id']
                : ($existing?->abs_jabatan_id ?? $this->resolveDefaultJabatanId($user)),
            'sub_company_id' => array_key_exists('sub_company_id', $attributes)
                ? $attributes['sub_company_id']
                : ($existing?->sub_company_id ?? $this->resolveDefaultSubCompanyId($user)),
            'abs_shift_id' => array_key_exists('abs_shift_id', $attributes)
                ? $attributes['abs_shift_id']
                : ($existing?->abs_shift_id ?? $this->resolveDefaultShiftId($user)),
            'company_id' => $user->company_id,
        ];

        return AbsEmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            $payload
        );
    }

    public function syncFromRequest(User $user, array $input): AbsEmployeeProfile
    {
        $attributes = [];

        if (!empty($input['position_uuid'])) {
            $attributes['abs_jabatan_id'] = Position::where('uuid', $input['position_uuid'])
                ->where('company_id', $user->company_id)
                ->value('id');
        }

        if (!empty($input['sub_company_uuid'])) {
            $attributes['sub_company_id'] = SubCompany::where('uuid', $input['sub_company_uuid'])
                ->where('company_id', $user->company_id)
                ->value('id');
        }

        if (!empty($input['shift_uuid'])) {
            $attributes['abs_shift_id'] = AbsShift::where('uuid', $input['shift_uuid'])
                ->where('company_id', $user->company_id)
                ->value('id');
        }

        return $this->syncForUser($user, $attributes);
    }

    public function ensureAllUsersHaveProfiles(int $companyId): int
    {
        $count = 0;

        User::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->each(function (User $user) use (&$count) {
                $this->syncForUser($user);
                $count++;
            });

        return $count;
    }

    protected function resolveDefaultSubCompanyId(User $user): ?int
    {
        if ($user->role === Role::MANDOR) {
            return SubCompany::where('mandor_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->value('id');
        }

        return SubCompany::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');
    }

    protected function resolveDefaultShiftId(User $user): ?int
    {
        return AbsShift::where('company_id', $user->company_id)
            ->orderBy('name')
            ->value('id');
    }

    protected function resolveDefaultJabatanId(User $user): ?int
    {
        $role = $user->role->value;
        if ($role == Role::SUPERADMIN->value) {
            $role = 'Super Admin';
        }

        return Position::where('company_id', $user->company_id)
            ->where('name', Str::title(str_replace('_', ' ', $role)))
            ->orderBy('name')
            ->value('id');
    }
}
