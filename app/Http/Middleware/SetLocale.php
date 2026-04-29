<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Ambil header Accept-Language, default ke 'id'
        $locale = $request->header('Accept-Language', 'id');
        
        // Validasi hanya 'id' atau 'en'
        if (in_array($locale, ['id', 'en'])) {
            app()->setLocale($locale);
        }
        
        return $next($request);
    }
}