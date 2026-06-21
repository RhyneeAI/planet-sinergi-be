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

        $mandorProofUrls = $fileService->urls($this->mandor_proof_files ?? []);

        return [
            'uuid' => (string) $this->uuid,
            'status' => $this->status?->value,
            'confirmed_amount' => $this->confirmed_amount !== null ? (float) $this->confirmed_amount : null,
            'mandor_proof_files' => $mandorProofUrls,
            'mandor_proof_file' => $mandorProofUrls[0] ?? null,
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
