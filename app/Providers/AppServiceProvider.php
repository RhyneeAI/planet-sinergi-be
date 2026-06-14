<?php

namespace App\Providers;

use Carbon\Carbon;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'users' => \App\Models\User::class,
            'ops_incomes' => \App\Models\OpsIncome::class,
            'ops_expenses' => \App\Models\OpsExpense::class,
            'ops_mandor_expenses' => \App\Models\OpsExpense::class,
            'ops_transfer_confirmations' => \App\Models\OpsTransferConfirmation::class,
        ]);

        RateLimiter::for('api', function ($request) {
            if (!app()->environment('testing')) {
                $method = $request->method();

                if (in_array($method, ['POST'])) {
                    $limit = Limit::perSecond(1, 6);
                } else if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
                    $limit = Limit::perSecond(1, 3);
                } else {
                    $limit = Limit::perMinute(80);
                }

                return $limit->by($request->user()?->id ?: $request->ip());
            }
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
