<?php

namespace App\Http\Resources\Operational;

use App\Services\Operational\OpsFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpsIncomeResource extends JsonResource
{
    use MapsOperationalProofFiles;

    public function toArray(Request $request): array
    {
        $fileService = app(OpsFileService::class);

        return [
            'uuid' => (string) $this->uuid,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            ...$this->mapProofFiles($fileService),
            'note' => $this->note,
            'source_type' => $this->source_type?->value,
            'mandor' => $this->whenLoaded('mandor', fn() => [
                'uuid' => $this->mandor->uuid,
                'name' => $this->mandor->name,
            ]),
            'sub_company' => $this->whenLoaded('subCompany', fn () => [
                'uuid' => $this->subCompany->uuid,
                'name' => $this->subCompany->name,
                'code' => $this->subCompany->code,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'uuid' => $this->createdBy->uuid,
                'name' => $this->createdBy->name,
            ]),
            'edit_logs' => $this->whenLoaded('editLogs', fn() => OpsEditLogResource::collection($this->editLogs)),
            'transfer_confirmation' => $this->whenLoaded(
                'transferConfirmation',
                fn() => new OpsTransferConfirmationResource($this->transferConfirmation)
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
