<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(\App\Observers\UserObserver::class);

        Relation::enforceMorphMap([
            'users' => \App\Models\User::class,
            'ops_incomes' => \App\Models\OpsIncome::class,
            'ops_expenses' => \App\Models\OpsExpense::class,
            'ops_mandor_expenses' => \App\Models\OpsExpense::class,
            'ops_transfer_confirmations' => \App\Models\OpsTransferConfirmation::class,
        ]);

        RateLimiter::for('api', function ($request) {
            if (app()->environment('testing')) {
                return;
            }

            $key = $request->user()?->id ?: $request->ip();

            if ($request->user()) {
                return Limit::perMinute(120)->by($key);
            }

            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                return Limit::perMinute(30)->by($key);
            }

            return Limit::perMinute(80)->by($key);
        });

        Carbon::macro('shortDiffForHumans', function () {
            $locale = app()->getLocale();
            $minutes = (int) $this->diffInMinutes(now());

            if ($minutes < 60) {
                return $minutes . 'm ' . ($locale === 'id' ? 'lalu' : 'ago');
            }

            $hours = (int) ($minutes / 60);
            if ($hours < 24) {
                $unit = $locale === 'id' ? 'j' : 'h';
                return $hours . $unit . ' ' . ($locale === 'id' ? 'lalu' : 'ago');
            }

            $days = (int) ($hours / 24);
            return $days . 'h ' . ($locale === 'id' ? 'lalu' : 'ago');
        });
    }
}
