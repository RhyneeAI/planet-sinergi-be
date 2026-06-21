<?php

namespace App\Services;

use App\Helpers\FileHelper;
use App\Jobs\GenerateReportExport;
use App\Models\ExportToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportService
{
    public function resolveCache(Request $request, string $reportType, array $filters, string $format, string $subfolder): ?array
    {
        $companyId = $request->user()->company_id;

        $hash = $this->cacheHash($companyId, $reportType, $filters, $format);
        $path = 'reports/' . $subfolder . '/' . $hash . '.' . $format;

        if (FileHelper::fileExists($path)) {
            return [
                'download_url' => FileHelper::downloadUrl($path),
                'filename' => basename($path),
                'cached' => true,
            ];
        }

        return null;
    }

    public function cacheHash(int $companyId, string $reportType, array $filters, string $format): string
    {
        return md5(implode('|', [
            $companyId,
            $reportType,
            json_encode($filters),
            $format,
        ]));
    }

    /**
     * After generating a file synchronously, save a cache alias so
     * subsequent identical requests skip regeneration.
     */
    public function saveCacheAlias(Request $request, string $reportType, array $filters, string $format, string $subfolder, string $generatedPath): void
    {
        $companyId = $request->user()->company_id;

        $hash = $this->cacheHash($companyId, $reportType, $filters, $format);
        $cachePath = 'reports/' . $subfolder . '/' . $hash . '.' . $format;

        if (! FileHelper::fileExists($cachePath)) {
            Storage::disk('public')->copy($generatedPath, $cachePath);
        }
    }

    public function enqueueOrFallback(
        Request $request,
        string $exportType,
        string $subfolder,
        string $filename,
        array $payload,
    ): ?array {
        if ($this->canUseQueue()) {
            $exportToken = ExportToken::create([
                'token' => (string) Str::uuid(),
                'report_type' => $subfolder,
                'filters' => $payload,
                'format' => $exportType,
                'status' => 'pending',
                'requested_by' => $request->user()->id,
                'company_id' => $request->user()->company_id,
            ]);

            dispatch(new GenerateReportExport(
                exportTokenId: $exportToken->id,
                exportType: $exportType,
                subfolder: $subfolder,
                filename: $filename,
                payload: $payload,
                companyId: $request->user()->company_id,
            ));

            return [
                'token' => $exportToken->token,
                'status' => 'processing',
            ];
        }

        return null;
    }

    public function canUseQueue(): bool
    {
        if (config('queue.default') !== 'redis') {
            return false;
        }

        try {
            app('redis')->connection()->command('ping');

            return true;
        } catch (\Exception $e) {
            Log::warning('Redis unavailable, falling back to synchronous export', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
