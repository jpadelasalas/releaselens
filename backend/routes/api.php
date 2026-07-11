<?php

use App\Http\Controllers\Api\V1\DemoSessionController;
use App\Http\Middleware\EnsureDemoSessionIsReadOnly;
use App\Http\Middleware\VerifyGitHubWebhookSignature;
use App\Modules\Analytics\Http\Controllers\OrganizationAnalyticsController;
use App\Modules\Deployments\Http\Controllers\DeploymentController;
use App\Modules\Deployments\Http\Controllers\EnvironmentMappingController;
use App\Modules\Deployments\Http\Controllers\LinkDeploymentReleaseController;
use App\Modules\GitHub\Http\Controllers\DisconnectGitHubConnectionController;
use App\Modules\GitHub\Http\Controllers\GitHubConnectionCallbackController;
use App\Modules\GitHub\Http\Controllers\ShowGitHubConnectionController;
use App\Modules\GitHub\Http\Controllers\StartGitHubConnectionController;
use App\Modules\Identity\Http\Controllers\CurrentUserController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\LogoutController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Operations\Http\Controllers\HealthController;
use App\Modules\Organizations\Http\Controllers\ActivateOrganizationController;
use App\Modules\Organizations\Http\Controllers\AddOrganizationMemberController;
use App\Modules\Organizations\Http\Controllers\CreateOrganizationController;
use App\Modules\Organizations\Http\Controllers\ListOrganizationMembersController;
use App\Modules\Organizations\Http\Controllers\ListOrganizationsController;
use App\Modules\Organizations\Http\Controllers\RemoveOrganizationMemberController;
use App\Modules\Organizations\Http\Controllers\UpdateOrganizationMemberController;
use App\Modules\PullRequests\Http\Controllers\PullRequestExplorerController;
use App\Modules\Releases\Http\Controllers\ExportReleaseMarkdownController;
use App\Modules\Releases\Http\Controllers\ReleaseApprovalController;
use App\Modules\Releases\Http\Controllers\ReleaseChecklistItemController;
use App\Modules\Releases\Http\Controllers\ReleaseController;
use App\Modules\Releases\Http\Controllers\ReleasePolicyController;
use App\Modules\Releases\Http\Controllers\ReleasePullRequestController;
use App\Modules\Repositories\Http\Controllers\AvailableGitHubRepositoriesController;
use App\Modules\Repositories\Http\Controllers\ImportRepositoriesController;
use App\Modules\Repositories\Http\Controllers\OrganizationRepositoryController;
use App\Modules\Repositories\Http\Controllers\UpdateRepositoryMonitoringController;
use App\Modules\Synchronization\Http\Controllers\ListRepositorySyncRunsController;
use App\Modules\Synchronization\Http\Controllers\RequestRepositorySyncController;
use App\Modules\Webhooks\Http\Controllers\ReceiveGitHubWebhookController;
use App\Modules\Webhooks\Http\Controllers\ShowSyncHealthController;
use App\Modules\Webhooks\Http\Controllers\WebhookDeliveryController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class)
    ->name('health.readiness');

Route::post('/v1/github/webhooks', ReceiveGitHubWebhookController::class)
    ->middleware(['feature:webhooks', VerifyGitHubWebhookSignature::class])
    ->name('github.webhooks.receive');

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

            Route::get('/github/callback', GitHubConnectionCallbackController::class)
                ->name('github.callback');

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

            Route::prefix('/organizations/{org}/members')
                ->whereNumber('org')
                ->group(function (): void {
                    Route::get('/', ListOrganizationMembersController::class)
                        ->name('organization-members.index');
                    Route::post('/', AddOrganizationMemberController::class)
                        ->name('organization-members.store');
                    Route::patch(
                        '/{member}',
                        UpdateOrganizationMemberController::class,
                    )
                        ->whereNumber('member')
                        ->name('organization-members.update');
                    Route::delete(
                        '/{member}',
                        RemoveOrganizationMemberController::class,
                    )
                        ->whereNumber('member')
                        ->name('organization-members.destroy');
                });

            Route::prefix('/organizations/{org}/github')
                ->whereNumber('org')
                ->group(function (): void {
                    Route::post('/connect', StartGitHubConnectionController::class)
                        ->name('github.connect');
                    Route::get('/connection', ShowGitHubConnectionController::class)
                        ->name('github.connection.show');
                    Route::delete('/connection', DisconnectGitHubConnectionController::class)
                        ->name('github.connection.destroy');
                });

            Route::get(
                '/organizations/{org}/github/available-repositories',
                AvailableGitHubRepositoriesController::class,
            )
                ->whereNumber('org')
                ->name('github.repositories.available');
            Route::post(
                '/organizations/{org}/repositories/import',
                ImportRepositoriesController::class,
            )
                ->whereNumber('org')
                ->name('repositories.import');
            Route::patch(
                '/organizations/{org}/repositories/{repository}',
                UpdateRepositoryMonitoringController::class,
            )
                ->whereNumber(['org', 'repository'])
                ->name('repositories.update');
            Route::post(
                '/organizations/{org}/repositories/{repository}/sync',
                RequestRepositorySyncController::class,
            )
                ->whereNumber(['org', 'repository'])
                ->name('repositories.sync');
            Route::get(
                '/organizations/{org}/repositories/{repository}/sync-runs',
                ListRepositorySyncRunsController::class,
            )
                ->whereNumber(['org', 'repository'])
                ->name('repositories.sync-runs.index');

            Route::prefix('/organizations/{org}/webhook-deliveries')
                ->whereNumber('org')
                ->middleware('feature:webhooks')
                ->controller(WebhookDeliveryController::class)
                ->group(function (): void {
                    Route::get('/', 'index')->name('webhook-deliveries.index');
                    Route::get('/{delivery}', 'show')
                        ->whereNumber('delivery')
                        ->name('webhook-deliveries.show');
                    Route::post('/{delivery}/replay', 'replay')
                        ->whereNumber('delivery')
                        ->name('webhook-deliveries.replay');
                });

            Route::get(
                '/organizations/{org}/sync-health',
                ShowSyncHealthController::class,
            )
                ->whereNumber('org')
                ->middleware('feature:webhooks')
                ->name('sync-health.show');

            Route::prefix('/organizations/{org}/releases')
                ->whereNumber('org')
                ->middleware('feature:releases')
                ->controller(ReleaseController::class)
                ->group(function (): void {
                    Route::get('/', 'index')->name('releases.index');
                    Route::post('/', 'store')->name('releases.store');
                    Route::get('/{release}', 'show')
                        ->whereNumber('release')
                        ->name('releases.show');
                    Route::patch('/{release}', 'update')
                        ->whereNumber('release')
                        ->name('releases.update');
                    Route::post('/{release}/transition', 'transition')
                        ->whereNumber('release')
                        ->name('releases.transition');
                });

            Route::get(
                '/organizations/{org}/releases/{release}/export.md',
                ExportReleaseMarkdownController::class,
            )
                ->whereNumber(['org', 'release'])
                ->middleware('feature:releases')
                ->name('releases.export-markdown');

            Route::prefix('/organizations/{org}/releases/{release}/pull-requests')
                ->whereNumber(['org', 'release'])
                ->middleware('feature:releases')
                ->controller(ReleasePullRequestController::class)
                ->group(function (): void {
                    Route::post('/', 'store')->name('release-pull-requests.store');
                    Route::delete('/{pullRequest}', 'destroy')
                        ->whereNumber('pullRequest')
                        ->name('release-pull-requests.destroy');
                });

            Route::prefix('/organizations/{org}/releases/{release}/checklist-items')
                ->whereNumber(['org', 'release'])
                ->middleware('feature:releases')
                ->controller(ReleaseChecklistItemController::class)
                ->group(function (): void {
                    Route::post('/', 'store')->name('release-checklist-items.store');
                    Route::patch('/{item}', 'update')
                        ->whereNumber('item')
                        ->name('release-checklist-items.update');
                    Route::delete('/{item}', 'destroy')
                        ->whereNumber('item')
                        ->name('release-checklist-items.destroy');
                });

            Route::post(
                '/organizations/{org}/releases/{release}/approvals',
                [ReleaseApprovalController::class, 'store'],
            )
                ->whereNumber(['org', 'release'])
                ->middleware('feature:releases')
                ->name('release-approvals.store');

            Route::prefix('/organizations/{org}/release-policy')
                ->whereNumber('org')
                ->middleware('feature:releases')
                ->controller(ReleasePolicyController::class)
                ->group(function (): void {
                    Route::get('/', 'show')->name('release-policy.show');
                    Route::put('/', 'update')->name('release-policy.update');
                });

            Route::prefix('/organizations/{org}/deployments')
                ->whereNumber('org')
                ->middleware('feature:deployments')
                ->controller(DeploymentController::class)
                ->group(function (): void {
                    Route::get('/', 'index')->name('deployments.index');
                    Route::get('/{deployment}', 'show')
                        ->whereNumber('deployment')
                        ->name('deployments.show');
                });

            Route::post(
                '/organizations/{org}/deployments/{deployment}/link-release',
                LinkDeploymentReleaseController::class,
            )
                ->whereNumber(['org', 'deployment'])
                ->middleware('feature:deployments')
                ->name('deployments.link-release');

            Route::prefix('/organizations/{org}/environment-mappings')
                ->whereNumber('org')
                ->middleware('feature:deployments')
                ->controller(EnvironmentMappingController::class)
                ->group(function (): void {
                    Route::get('/', 'index')->name('environment-mappings.index');
                    Route::post('/', 'store')->name('environment-mappings.store');
                });
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
