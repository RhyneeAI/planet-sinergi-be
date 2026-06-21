<?php

namespace App\Http\Controllers\Api\Operational;

use App\Models\OpsExpense;
use App\Models\OpsIncome;
use Illuminate\Http\Request;

trait HandlesOperationalProofFiles
{
    protected function storeProofFilesFromRequest(Request $request, string $type = 'income', string $role = 'admin'): array
    {
        return $this->fileService->storeProofs($this->proofUploadsFromRequest($request), $type, $role);
    }

    protected function proofUploadsFromRequest(Request $request): array
    {
        if ($request->hasFile('proof_files')) {
            $files = $request->file('proof_files');

            return is_array($files) ? array_values($files) : [$files];
        }

        return [];
    }

    protected function requestHasProofUpload(Request $request): bool
    {
        return $request->hasFile('proof_files');
    }

    protected function replaceProofFilesOnUpdate(Request $request, OpsIncome|OpsExpense $record, string $type = 'income', string $role = 'admin'): ?array
    {
        $uploads = $this->proofUploadsFromRequest($request);

        if ($uploads === []) {
            return null;
        }

        $this->fileService->deleteProofs($record->proof_files ?? []);

        return $this->fileService->storeProofs($uploads, $type, $role);
    }

    protected function deleteRecordProofs(OpsIncome|OpsExpense $record): void
    {
        $this->fileService->deleteProofs($record->proof_files ?? []);
    }

    protected function auditablePayload(OpsIncome|OpsExpense $record): array
    {
        return $record->only(['name', 'amount', 'date', 'payment_method', 'proof_files', 'note']);
    }
}
