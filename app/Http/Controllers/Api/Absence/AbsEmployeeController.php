<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Resources\Absence\AbsEmployeeDetailResource;
use App\Http\Resources\Absence\AbsEmployeeListResource;
use App\Models\User;
use Illuminate\Http\Request;

class AbsEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = User::with('absEmployeeProfile.jabatan', 'absEmployeeProfile.subCompany', 'absEmployeeProfile.shift')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy($request->input('order_by', 'name'), $request->input('order_by_value', 'ASC'))
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('absence.employees.list'),
            'data' => AbsEmployeeListResource::collection($employees),
        ]);
    }

    public function show(User $user)
    {
        $user->load([
            'absEmployeeProfile.jabatan',
            'absEmployeeProfile.subCompany',
            'absEmployeeProfile.shift',
            'absOvertimes' => fn($q) => $q->orderBy('date', 'DESC')->limit(50),
            'absLoans' => fn($q) => $q->orderBy('created_at', 'DESC')->limit(20),
            'absPayrollPeriods' => fn($q) => $q->orderBy('period_year', 'DESC')->orderBy('period_month', 'DESC')->limit(12),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('absence.employees.detail'),
            'data' => new AbsEmployeeDetailResource($user),
        ]);
    }
}
