<?php

namespace App\Jobs;

use App\Helpers\FileHelper;
use App\Models\ExportToken;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $exportTokenId,
        public string $exportType,
        public string $subfolder,
        public string $filename,
        public array $payload,
        public ?int $companyId = null,
    ) {
        $this->onConnection(config('queue.default'));
    }

    public function handle(): void
    {
        $token = ExportToken::find($this->exportTokenId);

        if (! $token) {
            Log::error('GenerateReportExport: ExportToken not found', ['id' => $this->exportTokenId]);

            return;
        }

        try {
            $token->update(['status' => 'processing']);

            $storagePath = 'reports/' . $this->subfolder . '/' . $this->filename;

            if ($this->exportType === 'xlsx') {
                $this->generateXlsx($storagePath);
            } elseif ($this->exportType === 'pdf') {
                $this->generatePdf($storagePath);
            }

            $token->update([
                'status' => 'completed',
                'disk_path' => $storagePath,
                'filename' => $this->filename,
                'completed_at' => now(),
            ]);

            Log::info('GenerateReportExport completed', [
                'token' => $token->token,
                'path' => $storagePath,
            ]);
        } catch (\Throwable $e) {
            $token->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('GenerateReportExport failed', [
                'token' => $token->token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function generateXlsx(string $storagePath): void
    {
        $exportClass = $this->payload['class'] ?? null;

        if ($exportClass && class_exists($exportClass)) {
            $instance = new $exportClass(...$this->payload['constructor_args']);
        } else {
            $exportClass = \App\Exports\AbsReportExport::class;
            $instance = new $exportClass(
                $this->payload['headers'] ?? [],
                collect($this->payload['rows'] ?? [])
            );
        }

        FileHelper::saveExcel($instance, $storagePath);
    }

    protected function generatePdf(string $storagePath): void
    {
        $view = $this->payload['view'] ?? null;

        if (! $view) {
            throw new \InvalidArgumentException('PDF export requires a "view" in payload');
        }

        $pdf = Pdf::loadView($view, $this->payload['data'] ?? [])
            ->setPaper(
                $this->payload['paper'] ?? 'a4',
                $this->payload['orientation'] ?? 'portrait'
            );

        FileHelper::saveFile($storagePath, $pdf->output());
    }
}
