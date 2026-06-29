<?php

namespace App\Http\Controllers\Api\Pos;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\DataTablesResponse;
use App\Models\User;
use Illuminate\Http\Request;

class PosMarketingController extends Controller
{
    use DataTablesResponse;

    protected array $sortableColumns = ['name', 'phone', 'email', 'created_at']; // ← username → phone

    public function index(Request $request)
    {
        $orderByKey   = in_array($request->input('order_by_key', 'name'), $this->sortableColumns)
                            ? $request->input('order_by_key', 'name')
                            : 'name';
        $orderByValue = strtoupper($request->input('order_by_value', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $marketings = User::where('role', Role::MARKETING)
            ->when($request->search, function ($query, $search) {
                $searchLower = strtolower($search);
                $query->where(function ($q) use ($searchLower) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $searchLower . '%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $searchLower . '%']) // ← username → phone
                      ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
                });
            })
            ->orderBy($orderByKey, $orderByValue)
            ->paginate($request->input('per_page', 15));

        return response()->json(
            $this->dataTablesResponse($request, $marketings, [
                'success' => true,
                'message' => __('pos.marketings.list'),
                'data'    => UserResource::collection($marketings),
            ])
        );
    }

    public function show(User $marketing)
    {
        return response()->json([
            'success' => true,
            'message' => __('pos.marketings.detail'),
            'data'    => new UserResource($marketing),
        ]);
    }
}
