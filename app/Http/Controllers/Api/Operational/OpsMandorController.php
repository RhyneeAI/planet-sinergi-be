<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsMandorResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OpsMandorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : today()->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : today()->endOfDay();

        $mandors = User::whereIn('role', [Role::MANDOR, Role::KEPALA_MANDOR])
            ->where('is_active', true)
            ->when(in_array($user->role, [Role::MANDOR, Role::KEPALA_MANDOR]), fn (Builder $query) => $query->where('id', $user->id))
            ->when($request->boolean('is_dashboard_data'), function ($query) use ($dateFrom, $dateTo) {
                $query
                    ->withSum([
                        'mandorIncomes as total_income' => function (Builder $q) use ($dateFrom, $dateTo) {
                            $q->whereBetween('date', [$dateFrom, $dateTo]);
                        }
                    ], 'amount')
                    ->withSum([
                        'mandorExpenses as total_expense' => function (Builder $q) use ($dateFrom, $dateTo) {
                            $q->whereBetween('date', [$dateFrom, $dateTo]);
                        }
                    ], 'amount');
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            })
            ->with(['subCompanies' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.mandors.list'),
            'data' => OpsMandorResource::collection($mandors),
        ]);
    }
}
