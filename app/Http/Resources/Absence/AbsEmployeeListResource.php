<?php

namespace App\Http\Resources\Absence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsEmployeeListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->absEmployeeProfile;

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'position' => $profile?->position ? [
                'uuid' => (string) $profile->position->uuid,
                'name' => $profile->position->name,
                'daily_rate' => (float) $profile->position->daily_rate,
            ] : null,
            'sub_company' => $profile?->subCompany ? [
                'uuid' => (string) $profile->subCompany->uuid,
                'name' => $profile->subCompany->name,
            ] : null,
            'shift' => $profile?->shift ? [
                'uuid' => (string) $profile->shift->uuid,
                'name' => $profile->shift->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
