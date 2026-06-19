<?php

namespace App\Http\Resources\Operational;

use App\Services\Operational\OpsFileService;

trait MapsOperationalProofFiles
{
  /**
   * @return array{proof_files: array<int, string>, proof_file: ?string}
   */
    protected function mapProofFiles(OpsFileService $fileService): array
    {
        $urls = $fileService->urls($this->proof_files ?? []);

        return [
            'proof_files' => $urls,
            'proof_file' => $urls[0] ?? null,
        ];
    }
}
