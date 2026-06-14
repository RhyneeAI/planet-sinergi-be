<?php

namespace App\Http\Controllers\Api\Operational;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Operational\OpsMandorResource;
use App\Models\User;
use Illuminate\Http\Request;

class OpsMandorController extends Controller
{
    public function index(Request $request)
    {
        $mandors = User::where('role', Role::MANDOR)
            ->where('is_active', true)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                        ->orWhereRaw('LOWER(username) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            })
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => __('operational.mandors.list'),
            'data' => OpsMandorResource::collection($mandors),
        ]);
    }
}
