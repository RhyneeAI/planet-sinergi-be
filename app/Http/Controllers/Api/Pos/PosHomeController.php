<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\HomeRequest;
use App\Http\Resources\Pos\PosHomeResource;
use App\Models\PosCustomer;
use App\Models\PosProduct;
use App\Models\PosSalesTransaction;
use App\Models\User;
use Carbon\Carbon;

class PosHomeController extends Controller
{
    public function index(HomeRequest $request)
    {
        $period = $request->input('period', 'day');
        $company_id = $request->user()->company_id;

        // Get date range based on period
        [$startDate, $endDate] = $this->getDateRange($period);

        // Count data
        $totalProducts   = PosProduct::where('company_id', $company_id)->count();
        $totalMarketing  = User::where('company_id', $company_id)
                                ->where('role', Role::MARKETING->value)
                                ->count();
        $totalCustomers  = PosCustomer::where('company_id', $company_id)->count();

        // Sales data filtered by period
        $salesQuery = PosSalesTransaction::where('company_id', $company_id)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        $totalSalesNominal   = $salesQuery->sum('total');
        $totalTransactions   = $salesQuery->count();

        return response()->json([
            'success' => true,
            'message' => __('pos.home.dashboard'),
            'data'    => [
                'period'                   => $period,
                'total_products'           => (int) $totalProducts,
                'total_marketing'          => (int) $totalMarketing,
                'total_customers'          => (int) $totalCustomers,
                'total_sales_nominal'      => (float) $totalSalesNominal ?? 0,
                'total_sales_transactions' => (int) $totalTransactions,
            ],
        ]);
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match($period) {
            'day'   => [
                $now->clone()->startOfDay(),
                $now->clone()->endOfDay(),
            ],
            'month' => [
                $now->clone()->startOfMonth(),
                $now->clone()->endOfMonth(),
            ],
            'year'  => [
                $now->clone()->startOfYear(),
                $now->clone()->endOfYear(),
            ],
            default => [
                $now->clone()->startOfDay(),
                $now->clone()->endOfDay(),
            ],
        };
    }
}
