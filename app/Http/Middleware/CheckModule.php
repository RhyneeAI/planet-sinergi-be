<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModule
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (!config("modules.{$module}.enabled", true)) {
            return response()->json([
                'success' => false,
                'message' => "Module {$module} tidak tersedia",
            ], 410);
        }

        return $next($request);
    }
}
