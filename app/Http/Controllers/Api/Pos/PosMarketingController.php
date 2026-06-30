<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pos\PosMarketingResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\User;
use Illuminate\Http\Request;

class PosMarketingController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'phone', 'email', 'created'];

    public function index(Request $request)
    {
        $orderByKey = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
            ? $request->input('order_by_key', 'name')
            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketings = User::with(['leaderUser'])
            ->where('company_id', $request->user()->company_id)
            ->whereIn('role', Role::posMarketingPickerValues())
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower($request->search);
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $search . '%'])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $search . '%']);
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $marketings, [
                'success' => true,
                'message' => __('pos.marketings.list'),
                'data'    => PosMarketingResource::collection($marketings),
            ])
        );
    }

    public function show(User $marketing)
    {
        if (!in_array($marketing->role?->value, Role::posMarketingPickerValues(), true)) {
            abort(response()->json([
                'success' => false,
                'message' => __('pos.marketings.not_found'),
                'code' => 404,
            ], 404));
        }

        $marketing->loadMissing(['leaderUser']);

        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.detail'),
            'data' => new PosMarketingResource($marketing),
        ]);
    }
}
