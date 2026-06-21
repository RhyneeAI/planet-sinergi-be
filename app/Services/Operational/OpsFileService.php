<?php

namespace App\Services\Operational;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OpsFileService
{
    public function storeProof(UploadedFile $file, string $type = 'income', string $role = 'admin'): string
    {
        return $file->store(
            config("operational.proof_directories.{$role}.{$type}", 'operational/proofs'),
            config('operational.proof_disk')
        );
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public function storeProofs(array $files, string $type = 'income', string $role = 'admin'): array
    {
        return array_map(fn (UploadedFile $file) => $this->storeProof($file, $type, $role), $files);
    }

    public function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk(config('operational.proof_disk'))->url($path);
    }

    /**
     * @param  array<int, string>|null  $paths
     * @return array<int, string>
     */
    public function urls(?array $paths): array
    {
        if (!$paths) {
            return [];
        }

        return array_values(array_filter(array_map(fn (?string $path) => $this->url($path), $paths)));
    }

    public function deleteProof(?string $path): void
    {
        if (!$path) {
            return;
        }

        Storage::disk(config('operational.proof_disk'))->delete($path);
    }

    /**
     * @param  array<int, string>|null  $paths
     */
    public function deleteProofs(?array $paths): void
    {
        foreach ($paths ?? [] as $path) {
            $this->deleteProof($path);
        }
    }
}
