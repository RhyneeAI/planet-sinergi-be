<?php

namespace App\Http\Resources\Operational;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsMarketingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof Role ? $this->role : Role::tryFrom((string) $this->role);

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'role' => $role?->value,
            'can_login' => false,
            'is_active' => (bool) $this->is_active,
            'leader' => $this->when(
                $role === Role::MARKETING && $this->relationLoaded('leaderUser'),
                fn () => $this->leaderUser->isNotEmpty()
                    ? new OpsMarketingLeaderSummaryResource($this->leaderUser->first())
                    : null
            ),
            'members' => $this->when(
                $role === Role::MARKETING_LEAD && $this->relationLoaded('memberUsers'),
                fn () => OpsMarketingLeaderSummaryResource::collection($this->memberUsers)
            ),
            'members_count' => $this->when(
                $role === Role::MARKETING_LEAD && isset($this->member_users_count),
                fn () => (int) $this->member_users_count
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
