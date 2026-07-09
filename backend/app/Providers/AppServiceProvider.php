<?php

namespace App\Providers;

use App\Modules\Analytics\Contracts\OrganizationAnalyticsRepositoryInterface;
use App\Modules\Analytics\Repositories\OrganizationAnalyticsRepository;
use App\Modules\GitHub\Clients\GitHubAppClient;
use App\Modules\GitHub\Contracts\GitHubAppClientInterface;
use App\Modules\GitHub\Contracts\GitHubConnectionRepositoryInterface;
use App\Modules\GitHub\Repositories\GitHubConnectionRepository;
use App\Modules\Identity\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Operations\Contracts\HealthCheckInterface;
use App\Modules\Operations\Services\DatabaseHealthCheck;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Organizations\Repositories\OrganizationWorkspaceRepository;
use App\Modules\PullRequests\Contracts\PullRequestRepositoryInterface;
use App\Modules\PullRequests\Repositories\PullRequestRepository;
use App\Modules\Releases\Contracts\ReleaseActivityRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseApprovalRepositoryInterface;
use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use App\Modules\Releases\Contracts\ReleaseRepositoryInterface;
use App\Modules\Releases\Repositories\ReleaseActivityRepository;
use App\Modules\Releases\Repositories\ReleaseApprovalRepository;
use App\Modules\Releases\Repositories\ReleasePolicyRepository;
use App\Modules\Releases\Repositories\ReleaseRepository;
use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Modules\Repositories\Repositories\OrganizationRepository;
use App\Modules\Synchronization\Contracts\GitHubRepositorySyncClientInterface;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Synchronization\Repositories\SynchronizationRepository;
use App\Modules\Synchronization\Services\GitHubRepositorySyncClient;
use App\Modules\Webhooks\Contracts\WebhookDeliveryRepositoryInterface;
use App\Modules\Webhooks\Handlers\InstallationRepositoriesWebhookHandler;
use App\Modules\Webhooks\Handlers\InstallationWebhookHandler;
use App\Modules\Webhooks\Handlers\PingWebhookHandler;
use App\Modules\Webhooks\Handlers\PullRequestReviewWebhookHandler;
use App\Modules\Webhooks\Handlers\PullRequestWebhookHandler;
use App\Modules\Webhooks\Handlers\RepositoryWebhookHandler;
use App\Modules\Webhooks\Repositories\WebhookDeliveryRepository;
use App\Modules\Webhooks\Support\WebhookEventHandlerRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            GitHubAppClientInterface::class,
            GitHubAppClient::class,
        );

        $this->app->bind(
            GitHubConnectionRepositoryInterface::class,
            GitHubConnectionRepository::class,
        );

        $this->app->bind(
            GitHubRepositorySyncClientInterface::class,
            GitHubRepositorySyncClient::class,
        );

        $this->app->bind(
            SynchronizationRepositoryInterface::class,
            SynchronizationRepository::class,
        );

        $this->app->bind(
            OrganizationAnalyticsRepositoryInterface::class,
            OrganizationAnalyticsRepository::class,
        );

        $this->app->bind(
            PullRequestRepositoryInterface::class,
            PullRequestRepository::class,
        );

        $this->app->bind(
            OrganizationRepositoryInterface::class,
            OrganizationRepository::class,
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class,
        );

        $this->app->bind(
            OrganizationWorkspaceRepositoryInterface::class,
            OrganizationWorkspaceRepository::class,
        );

        $this->app->bind(
            HealthCheckInterface::class,
            DatabaseHealthCheck::class,
        );

        $this->app->bind(
            WebhookDeliveryRepositoryInterface::class,
            WebhookDeliveryRepository::class,
        );

        $this->app->bind(
            ReleaseRepositoryInterface::class,
            ReleaseRepository::class,
        );

        $this->app->bind(
            ReleaseActivityRepositoryInterface::class,
            ReleaseActivityRepository::class,
        );

        $this->app->bind(
            ReleaseApprovalRepositoryInterface::class,
            ReleaseApprovalRepository::class,
        );

        $this->app->bind(
            ReleasePolicyRepositoryInterface::class,
            ReleasePolicyRepository::class,
        );

        $this->app->singleton(WebhookEventHandlerRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(
            OrganizationPolicy::VIEW,
            OrganizationPolicy::class.'@view',
        );
        Gate::define(
            OrganizationPolicy::MANAGE_MEMBERS,
            OrganizationPolicy::class.'@manageMembers',
        );
        Gate::define(
            OrganizationPolicy::MANAGE_GITHUB,
            OrganizationPolicy::class.'@manageGitHub',
        );
        Gate::define(
            OrganizationPolicy::DISCONNECT_GITHUB,
            OrganizationPolicy::class.'@disconnectGitHub',
        );
        Gate::define(
            OrganizationPolicy::MANAGE_REPOSITORIES,
            OrganizationPolicy::class.'@manageRepositories',
        );
        Gate::define(
            OrganizationPolicy::REQUEST_SYNCHRONIZATION,
            OrganizationPolicy::class.'@requestSynchronization',
        );
        Gate::define(
            OrganizationPolicy::MANAGE_RELEASES,
            OrganizationPolicy::class.'@manageReleases',
        );

        RateLimiter::for('demo-session', function (Request $request) {
            return Limit::perMinute(20)->by(
                ($request->hasSession()
                    ? $request->session()->getId()
                    : $request->ip()) ?: 'anonymous-demo-session'
            );
        });

        RateLimiter::for('authentication', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        $webhookHandlers = $this->app->make(WebhookEventHandlerRegistry::class);
        $webhookHandlers->register('ping', PingWebhookHandler::class);
        $webhookHandlers->register('pull_request', PullRequestWebhookHandler::class);
        $webhookHandlers->register('pull_request_review', PullRequestReviewWebhookHandler::class);
        $webhookHandlers->register('installation', InstallationWebhookHandler::class);
        $webhookHandlers->register('installation_repositories', InstallationRepositoriesWebhookHandler::class);
        $webhookHandlers->register('repository', RepositoryWebhookHandler::class);
    }
}
