<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FileHelper
{
    public static function saveFile(string $path, string $content, string $disk = 'public'): string
    {
        Storage::disk($disk)->put($path, $content);

        return $path;
    }

    public static function saveExcel(mixed $export, string $path, string $disk = 'public'): string
    {
        Excel::store($export, $path, $disk);

        return $path;
    }

    public static function downloadUrl(string $path, string $disk = 'public'): string
    {
        return Storage::disk($disk)->url($path);
    }

    public static function deleteFile(string $path, string $disk = 'public'): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    public static function fileExists(string $path, string $disk = 'public'): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    public static function exportResponse(string $path, string $filename, ?int $totalRows = null): array
    {
        return [
            'filename' => $filename,
            'download_url' => self::downloadUrl($path),
            'total_rows' => $totalRows,
        ];
    }
}
