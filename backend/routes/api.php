<?php

use App\Http\Controllers\Api\V1\DemoSessionController;
use App\Http\Middleware\EnsureDemoSessionIsReadOnly;
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
    });
