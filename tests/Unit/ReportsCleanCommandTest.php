<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function putReportFile(string $path, int $ageInDays): void
{
    Storage::disk('public')->put($path, 'dummy content');

    $filePath = Storage::disk('public')->path($path);
    touch($filePath, now()->subDays($ageInDays)->timestamp);
    clearstatcache(true, $filePath);
}

it('can delete old report files', function () {
    putReportFile('reports/pos/revenue/old-report.pdf', 5);
    putReportFile('reports/pos/revenue/new-report.pdf', 0);

    $this->artisan('reports:clean --days=3')
        ->assertExitCode(0);

    expect(Storage::disk('public')->exists('reports/pos/revenue/old-report.pdf'))->toBeFalse();
    expect(Storage::disk('public')->exists('reports/pos/revenue/new-report.pdf'))->toBeTrue();
});

it('does not delete files newer than specified days', function () {
    putReportFile('reports/pos/revenue/recent-report.pdf', 2);

    $this->artisan('reports:clean --days=3')
        ->assertExitCode(0);

    expect(Storage::disk('public')->exists('reports/pos/revenue/recent-report.pdf'))->toBeTrue();
});

it('handles empty directories gracefully', function () {
    $this->artisan('reports:clean --days=3')
        ->assertExitCode(0);
});

it('deletes files from both revenue and marketing-commission directories', function () {
    putReportFile('reports/pos/revenue/old-revenue.pdf', 10);
    putReportFile('reports/pos/marketing-commission/old-commission.pdf', 10);

    $this->artisan('reports:clean --days=7')
        ->assertExitCode(0);

    expect(Storage::disk('public')->exists('reports/pos/revenue/old-revenue.pdf'))->toBeFalse();
    expect(Storage::disk('public')->exists('reports/pos/marketing-commission/old-commission.pdf'))->toBeFalse();
});
