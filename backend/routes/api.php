<?php

use App\Http\Controllers\Api\V1\DemoSessionController;
use App\Http\Middleware\EnsureDemoSessionIsReadOnly;
use App\Modules\Analytics\Http\Controllers\OrganizationAnalyticsController;
use App\Modules\Identity\Http\Controllers\CurrentUserController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\LogoutController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Organizations\Http\Controllers\ActivateOrganizationController;
use App\Modules\Organizations\Http\Controllers\CreateOrganizationController;
use App\Modules\Organizations\Http\Controllers\ListOrganizationsController;
use App\Modules\PullRequests\Http\Controllers\PullRequestExplorerController;
use App\Modules\Repositories\Http\Controllers\OrganizationRepositoryController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('web')
    ->group(function (): void {
        Route::get('/auth/csrf-cookie', fn () => response()->noContent())
            ->name('auth.csrf-cookie');

        Route::middleware('throttle:authentication')->group(function (): void {
            Route::post('/auth/register', RegisterController::class)
                ->name('auth.register');
            Route::post('/auth/login', LoginController::class)
                ->name('auth.login');
        });

        Route::middleware('auth')->group(function (): void {
            Route::post('/auth/logout', LogoutController::class)
                ->name('auth.logout');
            Route::get('/me', CurrentUserController::class)
                ->name('auth.me');

            Route::get('/organizations', ListOrganizationsController::class)
                ->name('organizations.index');
            Route::post('/organizations', CreateOrganizationController::class)
                ->name('organizations.store');
            Route::post(
                '/organizations/{org}/activate',
                ActivateOrganizationController::class,
            )
                ->whereNumber('org')
                ->name('organizations.activate');
        });
    });

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

        Route::get(
            '/organizations/{org}/repositories',
            [OrganizationRepositoryController::class, 'index'],
        )
            ->whereNumber('org')
            ->name('repositories.index');

        Route::get(
            '/organizations/{org}/pull-requests',
            [PullRequestExplorerController::class, 'index'],
        )
            ->whereNumber('org')
            ->name('pull-requests.index');
    });
