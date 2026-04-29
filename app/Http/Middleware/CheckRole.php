<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code' => 401
            ], 401);
        }

        // Cek apakah role user termasuk dalam roles yang diizinkan
        if (!in_array($user->role->value, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'You don\'t have permission to access this resource.',
                'code' => 403
            ], 403);
        }

        return $next($request);
    }
}