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
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use App\Modules\Organizations\Repositories\OrganizationWorkspaceRepository;
use App\Modules\PullRequests\Contracts\PullRequestRepositoryInterface;
use App\Modules\PullRequests\Repositories\PullRequestRepository;
use App\Modules\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Modules\Repositories\Repositories\OrganizationRepository;
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
            GitHubAppClientInterface::class,
            GitHubAppClient::class,
        );

        $this->app->bind(
            GitHubConnectionRepositoryInterface::class,
            GitHubConnectionRepository::class,
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

        RateLimiter::for('authentication', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
