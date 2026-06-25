<?php

namespace App\Providers;

use App\Modules\Analytics\Contracts\OrganizationAnalyticsRepositoryInterface;
use App\Modules\Analytics\Repositories\OrganizationAnalyticsRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OrganizationAnalyticsRepositoryInterface::class,
            OrganizationAnalyticsRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('demo-session', function (Request $request) {
            return Limit::perMinute(20)->by(
                ($request->hasSession()
                    ? $request->session()->getId()
                    : $request->ip()) ?: 'anonymous-demo-session'
            );
        });
    }
}
