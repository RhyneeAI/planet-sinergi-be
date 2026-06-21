<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentType;
use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarketingCommissionRequest;
use App\Http\Requests\SalesRevenueRequest;
use App\Models\SalesDetail;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
    ) {}

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
                'details.product:id,uuid,name,code,sales_price,marketing_price',
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
                    $grossCommission += ($detail->sell_price - $detail->discount - $detail->marketing_price) * $detail->quantity;
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

        // Cache check
        $cacheFilters = $request->only(['date_from', 'date_to', 'marketing_uuid']);
        $cached = $this->exportService->resolveCache($request, 'marketing-commission', $cacheFilters, 'pdf', 'pos/marketing-commission');
        if ($cached) {
            return response()->json([
                'success' => true,
                'message' => 'Laporan komisi marketing berhasil dibuat.',
                'data'    => [
                    'period'       => $period,
                    'grand_total'  => $grandTotals,
                    'download_url' => $cached['download_url'],
                ],
            ]);
        }

        $pdf = Pdf::loadView('reports.marketing-commission', [
            'report'      => $report,
            'period'      => $period,
            'grand_total' => $grandTotals,
        ])->setPaper('a4', 'portrait');

        $filename    = 'marketing-commission-' . now()->format('YmdHis') . '.pdf';
        $storagePath = 'reports/pos/marketing-commission/' . $filename;

        FileHelper::saveFile($storagePath, $pdf->output());

        $this->exportService->saveCacheAlias($request, 'marketing-commission', $request->only(['date_from', 'date_to', 'marketing_uuid']), 'pdf', 'pos/marketing-commission', $storagePath);

        return response()->json([
            'success' => true,
            'message' => 'Laporan komisi marketing berhasil dibuat.',
            'data'    => [
                'period'       => $period,
                'grand_total'  => $grandTotals,
                'download_url' => FileHelper::downloadUrl($storagePath),
            ],
        ]);
    }

    public function salesRevenue(SalesRevenueRequest $request)
    {
        $companyId = $request->user()->company_id;

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

        // Ambil semua sales_details dalam rentang transaksi PAID
        $details = SalesDetail::with([
                'product:id,uuid,name,code,base_price,marketing_price,unit_id,sales_price', 
                'product.unit:id,name',                                         
                'saleTransaction:id,transaction_code,transaction_date,total,paid,additional_cost,payment_type,created_by',
                'saleTransaction.createdBy:id,name,role',                   
                'saleTransaction.installmentPlan:id,sales_transaction_id,paid_amount,total_amount,status',
            ])
            ->whereHas('saleTransaction', function ($q) use ($companyId, $request, $marketingId) {
                $q->where('company_id', $companyId)
                ->whereIn('transaction_status', [TransactionStatus::PAID, TransactionStatus::PROCESS, TransactionStatus::UNPAID])
                ->whereDate('transaction_date', '>=', $request->date_from)
                ->whereDate('transaction_date', '<=', $request->date_to);
                
                // Filter by marketing if provided
                if ($marketingId) {
                    $q->where('created_by', $marketingId);
                }
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
                    'unit'       => $first->product->unit->name ?? '-', 
                    'sell_price' => $rows->avg('sell_price'), 
                    'qty_sold'   => $qtySold,
                    'revenue'    => $revenue,
                ];
            })
            ->sortByDesc('qty_sold')
            ->take(10)
            ->values();

        $detailTransactions = $details->groupBy('saleTransaction.id')->map(function ($items, $transactionId) {
            $firstItem = $items->first();
            $trx = $firstItem->saleTransaction;
            $isCicil = $trx->payment_type === PaymentType::CICIL;
            $plan = $isCicil ? $trx->installmentPlan : null;
            
            // Cek apakah transaksi dibuat oleh OWNER atau MARKETING
            $createdBy = $trx->createdBy;
            $isOwner = $createdBy->role === Role::OWNER;
            
            // Hitung total keuntungan transaksi
            $totalProfitFull = $items->sum(function ($item) use ($isOwner) {
                $basePrice = $item->product->base_price;
                $profit = $isOwner 
                    ? $item->sell_price - $basePrice 
                    : $item->marketing_price - $basePrice;
                return $profit * $item->quantity;
            });

            if ($isCicil && $plan && $plan->status !== 'COMPLETED') {
                $totalProfit = 0;
            } else {
                $totalProfit = $totalProfitFull;
            }           
            
            return [
                'transaction_code' => $trx->transaction_code,
                'date'             => $trx->transaction_date->format('d/m/Y'),
                'cashier'          => $trx->createdBy->name ?? '-',
                'payment_type'     => $trx->payment_type?->value,
                'total'            => $trx->total - $trx->additional_cost,
                'profit'           => $totalProfit, 
                'is_cicil'         => $isCicil,
                'cicil_info'       => $isCicil ? [
                    'paid_amount'      => $plan?->paid_amount ?? 0,
                    'remaining_amount' => $plan?->remainingAmount() ?? 0,
                    'status'           => $plan?->status->label(),
                ] : null,
                'items'            => $items->map(function ($row) use ($isOwner) {
                    return [
                        'code'       => $row->product->code ?? '-',
                        'name'       => $row->product->name ?? '-',
                        'unit'       => $row->product->unit->name ?? '-',
						'base_price' => $row->product->base_price ?? '-',
                        'sales_price'=> $row->product->sales_price ?? '-',
                        'sell_price' => $row->sell_price,
                        'quantity'   => $row->quantity,
                        'subtotal'   => $row->subtotal,
                        'profit'     => $isOwner 
                            ? ($row->sell_price - $row->product->base_price) * $row->quantity
                            : ($row->marketing_price - $row->product->base_price) * $row->quantity,
                    ];
                })->values(),
            ];
        })->values();

        // Grand total
        $grandTotal = [
            'total_qty'       => $details->sum('quantity'),
            'total_revenue' => $details->groupBy('saleTransaction.id')->sum(function ($items) {
                $trx = $items->first()->saleTransaction;
                
                // Jika PAID atau UNPAID (lunas atau bayar langsung), pakai total
                if ($trx->transaction_status === TransactionStatus::PAID) {
                    return $trx->total;
                }
                
                // Jika CICIL, pakai paid_amount (yang sudah dibayar)
                if ($trx->payment_type === PaymentType::CICIL && $trx->installmentPlan) {
                    return $trx->installmentPlan->paid_amount;
                }
                
                // Jika PROCESS atau status lain, pakai paid (pembayaran yang sudah masuk)
                return $trx->paid;
            }),
            'total_profit'    => $detailTransactions->sum('profit'),
            'total_remaining' => $details
                ->filter(fn($r) => $r->saleTransaction->payment_type === PaymentType::CICIL)
                ->sum(fn($r) => $r->saleTransaction->installmentPlan?->remainingAmount() ?? 0),
        ];

        // Generate PDF
        $period = [
            'from' => Carbon::parse($request->date_from)->format('d M Y'),
            'to'   => Carbon::parse($request->date_to)->format('d M Y'),
        ];

        // Cache check
        $cacheFilters = $request->only(['date_from', 'date_to', 'marketing_uuid']);
        $cached = $this->exportService->resolveCache($request, 'sales-revenue', $cacheFilters, 'pdf', 'pos/revenue');
        if ($cached) {
            return response()->json([
                'success' => true,
                'message' => 'Laporan omset penjualan berhasil dibuat.',
                'data'    => [
                    'period'       => $period,
                    'grand_total'  => $grandTotal,
                    'download_url' => $cached['download_url'],
                ],
            ]);
        }

        $pdf = Pdf::loadView('reports.sales-revenue', [
            'top_products' => $topProducts,
            'details'      => $detailTransactions,
            'grand_total'  => $grandTotal,
            'period'       => $period,
        ])->setPaper('a4', 'landscape');

        $filename    = 'revenue-' . now()->format('YmdHis') . '.pdf';
        $storagePath = 'reports/pos/revenue/' . $filename;

        FileHelper::saveFile($storagePath, $pdf->output());

        $this->exportService->saveCacheAlias($request, 'sales-revenue', $cacheFilters, 'pdf', 'pos/revenue', $storagePath);

        return response()->json([
            'success' => true,
            'message' => 'Laporan omset penjualan berhasil dibuat.',
            'data'    => [
                'period'       => $period,
                'grand_total'  => $grandTotal,
                'download_url' => FileHelper::downloadUrl($storagePath),
            ],
        ]);
    }
}
