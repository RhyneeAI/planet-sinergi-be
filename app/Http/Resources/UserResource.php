<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role?->value,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'company_id' => $this->company_id,
            'sub_companies' => $this->when(
                $this->role === Role::MANDOR,
                fn () => $this->whenLoaded('subCompanies', function () {
                    return $this->subCompanies->map(fn ($subCompany) => [
                        'uuid' => (string) $subCompany->uuid,
                        'name' => $subCompany->name,
                        'code' => $subCompany->code,
                    ])->values();
                }, [])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
