<?php

use App\Http\Controllers\Api\V1\DemoSessionController;
use App\Http\Middleware\EnsureDemoSessionIsReadOnly;
use App\Modules\Analytics\Http\Controllers\OrganizationAnalyticsController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        SubstituteBindings::class,
        EnsureDemoSessionIsReadOnly::class,
    ])
    ->group(function (): void {
        Route::post('/demo/session', DemoSessionController::class)
            ->middleware('throttle:demo-session')
            ->name('demo.session');

        Route::prefix('/organizations/{org}/analytics')
            ->whereNumber('org')
            ->controller(OrganizationAnalyticsController::class)
            ->group(function (): void {
                Route::get('/summary', 'summary')->name('analytics.summary');
                Route::get('/trends', 'trends')->name('analytics.trends');
                Route::get('/distributions', 'distributions')->name('analytics.distributions');
                Route::get('/attention', 'attention')->name('analytics.attention');
            });
    });
