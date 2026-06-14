<?php

namespace App\Http\Resources\Operational;

use App\Services\Operational\OpsFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsTransferConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileService = app(OpsFileService::class);

        return [
            'uuid' => (string) $this->uuid,
            'status' => $this->status?->value,
            'mandor_proof_file' => $fileService->url($this->mandor_proof_file),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'note' => $this->note,
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn() => [
                'uuid' => $this->confirmedBy->uuid,
                'name' => $this->confirmedBy->name,
            ]),
            'confirmable' => $this->whenLoaded('confirmable', function () {
                if ($this->confirmable instanceof \App\Models\OpsIncome) {
                    return new OpsIncomeResource($this->confirmable);
                }

                if ($this->confirmable instanceof \App\Models\OpsExpense) {
                    return new OpsExpenseResource($this->confirmable);
                }

                return null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
