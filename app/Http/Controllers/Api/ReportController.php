<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarketingCommissionRequest;
use App\Http\Requests\SalesRevenueRequest;
use App\Models\SalesDetail;
use App\Models\SalesTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function marketingCommission(MarketingCommissionRequest $request)
    {
        $companyId   = $request->user()->company_id;

        // Resolve marketing_uuid → id jika ada
        $marketingId = null;
        if ($request->marketing_uuid) {
            $marketingId = User::where('uuid', $request->marketing_uuid)
                ->where('role', Role::MARKETING)
                ->where('company_id', $companyId)
                ->value('id');

            if (!$marketingId) {
                return response()->json([
                    'success' => false,
                    'message' => __('reports.validation.marketingCommission.marketing_not_found'),
                    'code'    => 404,
                ], 404);
            }
        }

        // Ambil semua user role marketing di company ini
        $marketingUserIds = User::where('company_id', $companyId)
            ->where('role', Role::MARKETING)
            ->pluck('id');

        $transactions = SalesTransaction::with([
                'customer:id,name',
                'createdBy:id,name,uuid',
                'createdBy.marketingProducts:id,marketing_id,product_id,marketing_price',
                'details.product:id,uuid,name,code,sales_price',
            ])
            ->where('company_id', $companyId)
            ->whereIn('created_by', $marketingUserIds) 
            ->where('transaction_status', TransactionStatus::PAID)
            ->whereDate('transaction_date', '>=', $request->date_from)
            ->whereDate('transaction_date', '<=', $request->date_to)
            ->when($marketingId, fn($q, $id) => $q->where('created_by', $id))
            ->orderBy('created_by')
            ->orderBy('transaction_date')
            ->get();

        // Build marketing_products map dari eager load
        // structure: created_by => [ product_id => marketing_price ]
        $mpMap = [];
        foreach ($transactions as $trx) {
            $createdBy = $trx->created_by;
            if (!isset($mpMap[$createdBy])) {
                $mpMap[$createdBy] = $trx->createdBy->marketingProducts
                    ->pluck('marketing_price', 'product_id')
                    ->toArray();
            }
        }

        // Build report
        $report      = [];
        $grandTotals = [
            'total_sales'      => 0,
            'total_discount'   => 0,
            'total_commission' => 0,
        ];

        $grouped = $transactions->groupBy('created_by');

        foreach ($grouped as $createdBy => $mktTransactions) {
            $marketing        = $mktTransactions->first()->createdBy;
            $marketingSummary = ['total_sales' => 0, 'total_commission' => 0];
            $transactionRows  = [];

            foreach ($mktTransactions as $trx) {
                $totalAfterDiscount = $trx->total - $trx->discount;

                // Hitung komisi kotor dari items
                $grossCommission = 0;
                foreach ($trx->details as $detail) {
                    $marketingPrice = $mpMap[$createdBy][$detail->product_id] ?? null;
                    if (!$marketingPrice) continue;

                    $grossCommission += ($detail->product->sales_price - $marketingPrice) * $detail->quantity;
                }

                // Diskon sepenuhnya ditanggung marketing
                $netCommission = max(0, $grossCommission - $trx->discount);

                $transactionRows[] = [
                    'date'                 => Carbon::parse($trx->transaction_date)->format('d/m/Y'),
                    'transaction_code'     => $trx->transaction_code,
                    'customer'             => $trx->customer?->name ?? 'Umum',
                    'payment_type'         => $trx->payment_type?->value,
                    'total'                => $trx->total,
                    'discount'             => $trx->discount,
                    'total_after_discount' => $totalAfterDiscount,
                    'commission'           => $netCommission,
                ];

                $marketingSummary['total_sales']      += $totalAfterDiscount;
                $marketingSummary['total_commission'] += $netCommission;
            }

            $report[] = [
                'marketing'    => [
                    'uuid' => $marketing->uuid,
                    'name' => $marketing->name,
                ],
                'transactions' => $transactionRows,
                'summary'      => $marketingSummary,
            ];

            $grandTotals['total_sales']      += $marketingSummary['total_sales'];
            $grandTotals['total_discount']   += $mktTransactions->sum('discount');
            $grandTotals['total_commission'] += $marketingSummary['total_commission'];
        }

        // Generate PDF
        $period = [
            'from' => Carbon::parse($request->date_from)->format('d M Y'),
            'to'   => Carbon::parse($request->date_to)->format('d M Y'),
        ];

        $pdf = Pdf::loadView('reports.marketing-commission', [
            'report'      => $report,
            'period'      => $period,
            'grand_total' => $grandTotals,
        ])->setPaper('a4', 'portrait');

        $filename    = 'laporan-komisi-marketing-' . now()->format('YmdHis') . '.pdf';
        $storagePath = 'reports/marketing-commission/' . $filename;

        Storage::disk('public')->put($storagePath, $pdf->output());

        $downloadUrl = $request->getSchemeAndHttpHost() . '/storage/' . $storagePath;

        return response()->json([
            'success' => true,
            'message' => 'Laporan komisi marketing berhasil dibuat.',
            'data'    => [
                'period'       => $period,
                'grand_total'  => $grandTotals,
                'download_url' => $downloadUrl,
            ],
        ]);
    }

    // Tambahkan method di dalam class ReportController
    public function salesRevenue(SalesRevenueRequest $request)
    {
        $companyId = $request->user()->company_id;

        // Ambil semua sales_details dalam rentang transaksi PAID
        $details = SalesDetail::with([
                'product:id,uuid,name,code',
                'saleTransaction:id,transaction_code,transaction_date,created_by',
                'saleTransaction.createdBy:id,name'
            ])
            ->whereHas('saleTransaction', function ($q) use ($companyId, $request) {
                $q->where('company_id', $companyId)
                ->where('transaction_status', TransactionStatus::PAID)
                ->whereDate('transaction_date', '>=', $request->date_from)
                ->whereDate('transaction_date', '<=', $request->date_to);
            })
            ->where('company_id', $companyId)
            ->get();

        // Build top 10 — kumulatif per produk
        $topProducts = $details
            ->groupBy('product_id')
            ->map(function ($rows) {
                $first    = $rows->first();
                $qtySold  = $rows->sum('quantity');
                $revenue  = $rows->sum(fn($r) => $r->quantity * $r->sell_price);

                return [
                    'product_id' => $first->product_id,
                    'code'       => $first->product->code ?? '-',
                    'name'       => $first->product->name ?? '-',
                    'sell_price' => $rows->avg('sell_price'), // rata-rata harga jual
                    'qty_sold'   => $qtySold,
                    'revenue'    => $revenue,
                ];
            })
            ->sortByDesc('qty_sold')
            ->take(10)
            ->values();

        // Build detail rows — semua transaksi, tidak di-limit
        $detailTransactions = $details->groupBy('saleTransaction.id')->map(function ($items, $transactionId) {
            $firstItem = $items->first();
            return [
                'transaction_code' => $firstItem->saleTransaction->transaction_code,
                'date'             => $firstItem->saleTransaction->transaction_date->format('d/m/Y'),
                'cashier'          => $firstItem->saleTransaction->createdBy->name ?? '-',
                'items'            => $items->map(function ($row) {
                    return [
                        'code'       => $row->product->code ?? '-',
                        'name'       => $row->product->name ?? '-',
                        'sell_price' => $row->sell_price,
                        'quantity'   => $row->quantity,
                        'revenue'    => $row->quantity * $row->sell_price,
                    ];
                })->values(),
            ];
        })->values();

        // Grand total
        $grandTotal = [
            'total_qty'     => $details->sum('quantity'),
            'total_revenue' => $details->sum(fn($r) => $r->quantity * $r->sell_price),
        ];

        // Generate PDF
        $period = [
            'from' => Carbon::parse($request->date_from)->format('d M Y'),
            'to'   => Carbon::parse($request->date_to)->format('d M Y'),
        ];

        $pdf = Pdf::loadView('reports.sales-revenue', [
            'top_products' => $topProducts,
            'details'      => $detailTransactions,
            'grand_total'  => $grandTotal,
            'period'       => $period,
        ])->setPaper('a4', 'landscape');

        $filename    = 'laporan-omset-penjualan-' . now()->format('YmdHis') . '.pdf';
        $storagePath = 'reports/revenue/' . $filename;

        Storage::disk('public')->put($storagePath, $pdf->output());

        $downloadUrl = $request->getSchemeAndHttpHost() . '/storage/' . $storagePath;
     
        return response()->json([
            'success' => true,
            'message' => 'Laporan omset penjualan berhasil dibuat.',
            'data'    => [
                'period'       => $period,
                'grand_total'  => $grandTotal,
                'download_url' => $downloadUrl,
            ],
        ]);
    }
}