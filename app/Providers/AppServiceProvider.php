<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            $method = $request->method();
            
            if (in_array($method, ['POST'])) {
                $limit = Limit::perSecond(1, 6);  
            } else if(in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
                $limit = Limit::perSecond(1, 3);   
            } else {
                $limit = Limit::perMinute(80);  
            }
            
            return $limit->by($request->user()?->id ?: $request->ip());
        });
    }
}
