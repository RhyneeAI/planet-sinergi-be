<?php

namespace App\Services\Operational;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OpsFileService
{
    public function storeProof(UploadedFile $file): string
    {
        return $file->store(
            config('operational.proof_directory'),
            config('operational.proof_disk')
        );
    }

    public function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk(config('operational.proof_disk'))->url($path);
    }

    public function deleteProof(?string $path): void
    {
        if (!$path) {
            return;
        }

        Storage::disk(config('operational.proof_disk'))->delete($path);
    }
}
