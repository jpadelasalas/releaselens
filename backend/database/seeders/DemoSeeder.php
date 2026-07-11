<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoSeeder extends Seeder
{
    private const DEMO_ORGANIZATION_SLUG = 'northstar-engineering';

    private const PULL_REQUEST_COUNT = 192;

    /**
     * Used to generate unique synthetic GitHub review IDs.
     */
    private int $reviewSequence = 9_400_000_000;

    private CarbonImmutable $anchor;

    private int $randomSeed;

    /**
     * Cached table column lists.
     *
     * @var array<string, array<int, string>>
     */
    private array $columnCache = [];

    public function run(): void
    {
        $this->assertRequiredTables();

        $this->anchor = CarbonImmutable::parse(
            (string) config(
                'releaselens.demo.anchor_date',
                '2026-06-19T12:00:00Z'
            )
        )->utc();

        $this->randomSeed = (int) config(
            'releaselens.demo.random_seed',
            1001
        );

        DB::transaction(function (): void {
            $this->deleteExistingDemoOrganization();

            $organizationId = $this->seedOrganization();

            $this->seedDemoOwnerMembership($organizationId);

            $actors = $this->seedGitHubUsers();

            $repositories = $this->seedRepositories($organizationId);

            $result = $this->seedPullRequests(
                repositories: $repositories,
                actors: $actors,
            );

            $this->seedSyncRuns($repositories);

            $this->seedWebhookDemoData($organizationId, $repositories);

            $this->seedReleaseDemoData($organizationId, $repositories);

            $this->command?->newLine();
            $this->command?->info('ReleaseLens demo data created.');
            $this->command?->line(
                'Organization: Northstar Engineering'
            );
            $this->command?->line(
                'Repositories: '.count($repositories)
            );
            $this->command?->line(
                'Pull requests: '.$result['pull_requests']
            );
            $this->command?->line(
                'Reviews: '.$result['reviews']
            );
            $this->command?->line(
                'Anchor: '.$this->anchor->toIso8601String()
            );
        });
    }

    private function assertRequiredTables(): void
    {
        $requiredTables = [
            'organizations',
            'repositories',
            'github_users',
            'pull_requests',
            'pull_request_reviews',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Required ReleaseLens table [{$table}] does not exist."
                );
            }
        }
    }

    /**
     * Delete only the existing synthetic demo tenant.
     *
     * This does not delete connected organizations or their data.
     */
    private function deleteExistingDemoOrganization(): void
    {
        $organizationId = DB::table('organizations')
            ->where('slug', $this->demoOrganizationSlug())
            ->value('id');

        if ($organizationId === null) {
            return;
        }

        $repositoryIds = Schema::hasTable('repositories')
            ? DB::table('repositories')
                ->where('organization_id', $organizationId)
                ->pluck('id')
            : collect();

        $pullRequestIds = collect();

        if (
            Schema::hasTable('pull_requests') &&
            $repositoryIds->isNotEmpty()
        ) {
            $pullRequestIds = DB::table('pull_requests')
                ->whereIn('repository_id', $repositoryIds)
                ->pluck('id');
        }

        if (
            Schema::hasTable('pull_request_reviews') &&
            $pullRequestIds->isNotEmpty()
        ) {
            DB::table('pull_request_reviews')
                ->whereIn('pull_request_id', $pullRequestIds)
                ->delete();
        }

        if (
            Schema::hasTable('pull_requests') &&
            $repositoryIds->isNotEmpty()
        ) {
            DB::table('pull_requests')
                ->whereIn('repository_id', $repositoryIds)
                ->delete();
        }

        if (
            Schema::hasTable('sync_runs') &&
            $repositoryIds->isNotEmpty()
        ) {
            $syncRunIds = DB::table('sync_runs')
                ->whereIn('repository_id', $repositoryIds)
                ->pluck('id');

            if (
                Schema::hasTable('sync_run_errors') &&
                $syncRunIds->isNotEmpty()
            ) {
                DB::table('sync_run_errors')
                    ->whereIn('sync_run_id', $syncRunIds)
                    ->delete();
            }

            DB::table('sync_runs')
                ->whereIn('repository_id', $repositoryIds)
                ->delete();
        }

        if (Schema::hasTable('webhook_deliveries')) {
            DB::table('webhook_deliveries')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (
            Schema::hasTable('deployments') &&
            $repositoryIds->isNotEmpty()
        ) {
            $deploymentIds = DB::table('deployments')
                ->whereIn('repository_id', $repositoryIds)
                ->pluck('id');

            if (
                Schema::hasTable('deployment_status_events') &&
                $deploymentIds->isNotEmpty()
            ) {
                DB::table('deployment_status_events')
                    ->whereIn('deployment_id', $deploymentIds)
                    ->delete();
            }

            DB::table('deployments')
                ->whereIn('repository_id', $repositoryIds)
                ->delete();
        }

        if (Schema::hasTable('releases')) {
            $releaseIds = DB::table('releases')
                ->where('organization_id', $organizationId)
                ->pluck('id');

            if ($releaseIds->isNotEmpty()) {
                foreach ([
                    'release_activities',
                    'release_approvals',
                    'release_checklist_items',
                    'release_pull_requests',
                    'release_repositories',
                ] as $childTable) {
                    if (Schema::hasTable($childTable)) {
                        DB::table($childTable)
                            ->whereIn('release_id', $releaseIds)
                            ->delete();
                    }
                }
            }

            DB::table('releases')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (Schema::hasTable('release_policies')) {
            DB::table('release_policies')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (Schema::hasTable('environment_mappings')) {
            DB::table('environment_mappings')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (
            Schema::hasTable('repositories') &&
            $repositoryIds->isNotEmpty()
        ) {
            DB::table('repositories')
                ->whereIn('id', $repositoryIds)
                ->delete();
        }

        if (Schema::hasTable('organization_members')) {
            DB::table('organization_members')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (Schema::hasTable('github_installations')) {
            DB::table('github_installations')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')
                ->where('organization_id', $organizationId)
                ->delete();
        }

        DB::table('organizations')
            ->where('id', $organizationId)
            ->delete();
    }

    private function seedOrganization(): int
    {
        return $this->insertGetId('organizations', [
            'name' => 'Northstar Engineering',
            'slug' => $this->demoOrganizationSlug(),
            'timezone' => 'Asia/Manila',
            'is_demo' => true,
            'created_at' => $this->anchor,
            'updated_at' => $this->anchor,
        ]);
    }

    private function demoOrganizationSlug(): string
    {
        return (string) config(
            'releaselens.demo.organization_slug',
            self::DEMO_ORGANIZATION_SLUG
        );
    }

    /**
     * The demo session does not authenticate as this user.
     *
     * This disabled account exists only when the schema requires an
     * organization member/owner relationship.
     */
    private function seedDemoOwnerMembership(
        int $organizationId
    ): void {
        if (
            ! Schema::hasTable('users') ||
            ! Schema::hasTable('organization_members')
        ) {
            return;
        }

        $email = 'demo-owner@releaselens.invalid';

        $userPayload = $this->filterPayload('users', [
            'name' => 'ReleaseLens Demo Owner',
            'email' => $email,
            'normalized_email' => mb_strtolower($email),
            'email_verified_at' => $this->anchor,
            'password' => Hash::make('disabled-demo-account'),
            'timezone' => 'Asia/Manila',
            'disabled_at' => $this->anchor,
            'created_at' => $this->anchor,
            'updated_at' => $this->anchor,
        ]);

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            $userPayload
        );

        $userId = DB::table('users')
            ->where('email', $email)
            ->value('id');

        if ($userId === null) {
            throw new RuntimeException(
                'Unable to create the disabled demo owner.'
            );
        }

        DB::table('organization_members')->insert(
            $this->filterPayload('organization_members', [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'role' => 'owner',
                'joined_at' => $this->anchor,
                'created_at' => $this->anchor,
                'updated_at' => $this->anchor,
            ])
        );
    }

    /**
     * @return array{
     *     developers: array<int, array<string, mixed>>,
     *     bots: array<int, array<string, mixed>>
     * }
     */
    private function seedGitHubUsers(): array
    {
        $definitions = [
            [
                'github_user_id' => 9_100_000_001,
                'login' => 'ava-stone',
                'name' => 'Ava Stone',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_002,
                'login' => 'liam-chen',
                'name' => 'Liam Chen',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_003,
                'login' => 'maya-rodriguez',
                'name' => 'Maya Rodriguez',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_004,
                'login' => 'noah-williams',
                'name' => 'Noah Williams',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_005,
                'login' => 'sofia-reyes',
                'name' => 'Sofia Reyes',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_006,
                'login' => 'ethan-brooks',
                'name' => 'Ethan Brooks',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_007,
                'login' => 'isla-patel',
                'name' => 'Isla Patel',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_008,
                'login' => 'lucas-tan',
                'name' => 'Lucas Tan',
                'type' => 'User',
                'is_bot' => false,
            ],
            [
                'github_user_id' => 9_100_000_101,
                'login' => 'dependabot[bot]',
                'name' => 'Dependabot',
                'type' => 'Bot',
                'is_bot' => true,
            ],
            [
                'github_user_id' => 9_100_000_102,
                'login' => 'northstar-ci[bot]',
                'name' => 'Northstar CI',
                'type' => 'Bot',
                'is_bot' => true,
            ],
        ];

        $developers = [];
        $bots = [];

        foreach ($definitions as $definition) {
            $payload = $this->filterPayload('github_users', [
                'github_user_id' => $definition['github_user_id'],
                'login' => $definition['login'],
                'name' => $definition['name'],
                'display_name' => $definition['name'],
                'type' => $definition['type'],
                'account_type' => $definition['type'],
                'is_bot' => $definition['is_bot'],
                'avatar_url' => null,
                'created_at' => $this->anchor,
                'updated_at' => $this->anchor,
            ]);

            DB::table('github_users')->updateOrInsert(
                [
                    'github_user_id' => $definition['github_user_id'],
                ],
                $payload
            );

            $localId = DB::table('github_users')
                ->where(
                    'github_user_id',
                    $definition['github_user_id']
                )
                ->value('id');

            if ($localId === null) {
                throw new RuntimeException(
                    "Unable to seed GitHub user {$definition['login']}."
                );
            }

            $actor = [
                ...$definition,
                'id' => (int) $localId,
            ];

            if ($definition['is_bot']) {
                $bots[] = $actor;
            } else {
                $developers[] = $actor;
            }
        }

        return [
            'developers' => $developers,
            'bots' => $bots,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seedRepositories(
        int $organizationId
    ): array {
        $definitions = [
            [
                'github_repository_id' => 9_200_000_001,
                'name' => 'customer-portal',
                'description' => 'Customer account and subscription portal.',
            ],
            [
                'github_repository_id' => 9_200_000_002,
                'name' => 'billing-api',
                'description' => 'Billing and payment service API.',
            ],
            [
                'github_repository_id' => 9_200_000_003,
                'name' => 'mobile-shell',
                'description' => 'Shared mobile application foundation.',
            ],
            [
                'github_repository_id' => 9_200_000_004,
                'name' => 'developer-tools',
                'description' => 'Internal developer productivity utilities.',
            ],
        ];

        $repositories = [];

        foreach ($definitions as $index => $definition) {
            $lastSuccessfulSync = $this->anchor
                ->subMinutes(15 + ($index * 11));

            $repositoryId = $this->insertGetId(
                'repositories',
                [
                    'organization_id' => $organizationId,
                    'github_installation_id' => null,
                    'github_repository_id' => $definition['github_repository_id'],
                    'name' => $definition['name'],
                    'full_name' => 'northstar-engineering/'.
                        $definition['name'],
                    'description' => $definition['description'],
                    'visibility' => 'public',
                    'default_branch' => 'main',
                    'html_url' => null,
                    'is_archived' => false,
                    'sync_enabled' => true,
                    'sync_status' => 'successful',
                    'last_sync_at' => $lastSuccessfulSync,
                    'last_successful_sync_at' => $lastSuccessfulSync,
                    'created_at' => $this->anchor->subWeeks(20),
                    'updated_at' => $lastSuccessfulSync,
                ]
            );

            $repositories[] = [
                ...$definition,
                'id' => $repositoryId,
                'last_successful_sync_at' => $lastSuccessfulSync,
            ];
        }

        return $repositories;
    }

    /**
     * @param  array<int, array<string, mixed>>  $repositories
     * @param array{
     *     developers: array<int, array<string, mixed>>,
     *     bots: array<int, array<string, mixed>>
     * } $actors
     * @return array{pull_requests: int, reviews: int}
     */
    private function seedPullRequests(
        array $repositories,
        array $actors
    ): array {
        $reviewCount = 0;

        for ($index = 1; $index <= self::PULL_REQUEST_COUNT; $index++) {
            $repository = $repositories[($index - 1) % count($repositories)];

            $author = $actors['developers'][(($index * 3) + $this->randomSeed)
                % count($actors['developers'])];

            $scenario = $this->scenarioFor($index);

            $createdAt = $this->anchor->subHours(
                $scenario['hours_ago']
            );

            $ageHours = max(
                1,
                (int) $createdAt->diffInHours($this->anchor)
            );

            $isOpen = $scenario['is_open'];
            $isDraft = $scenario['is_draft'];
            $isClosedWithoutMerge =
                $scenario['is_closed_without_merge'];

            $reviewDelayHours = min(
                $scenario['review_delay_hours'],
                max(1, $ageHours - 1)
            );

            $lifecycleDelayHours = min(
                max(
                    $reviewDelayHours + 2,
                    $scenario['lifecycle_delay_hours']
                ),
                $ageHours
            );

            $closedAt = null;
            $mergedAt = null;

            if (! $isOpen) {
                $closedAt = $createdAt->addHours(
                    $lifecycleDelayHours
                );

                if (! $isClosedWithoutMerge) {
                    $mergedAt = $closedAt;
                }
            }

            $state = $isOpen ? 'open' : 'closed';

            $updatedCandidate = $closedAt
                ?? $createdAt->addHours(
                    min(
                        $ageHours,
                        6 + (($index * 7) % 72)
                    )
                );

            $updatedAtGitHub =
                $updatedCandidate->greaterThan($this->anchor)
                ? $this->anchor
                : $updatedCandidate;

            $changeSize = $scenario['change_size'];

            $additions = (int) floor($changeSize * 0.72);
            $deletions = $changeSize - $additions;

            $pullRequestId = $this->insertGetId(
                'pull_requests',
                [
                    'repository_id' => $repository['id'],
                    'github_pull_request_id' => 9_300_000_000 + $index,
                    'number' => 1000 + $index,
                    'title' => $this->pullRequestTitle(
                        $repository['name'],
                        $index
                    ),
                    'html_url' => null,
                    'state' => $state,
                    'is_draft' => $isDraft,
                    'author_github_user_id' => $author['id'],
                    'base_ref' => 'main',
                    'head_ref' => 'feature/demo-'.str_pad(
                        (string) $index,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'additions' => $additions,
                    'deletions' => $deletions,
                    'changed_files' => max(1, (int) ceil($changeSize / 80)),
                    'commits_count' => 1 + (($index * 5) % 14),
                    'comments_count' => ($index * 3) % 12,
                    'created_at_github' => $createdAt,
                    'updated_at_github' => $updatedAtGitHub,
                    'closed_at' => $closedAt,
                    'merged_at' => $mergedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAtGitHub,
                ]
            );

            $reviewCount += $this->seedReviewsForPullRequest(
                index: $index,
                pullRequestId: $pullRequestId,
                author: $author,
                actors: $actors,
                createdAt: $createdAt,
                reviewDelayHours: $reviewDelayHours,
                mode: $scenario['review_mode'],
            );
        }

        return [
            'pull_requests' => self::PULL_REQUEST_COUNT,
            'reviews' => $reviewCount,
        ];
    }

    /**
     * Generate important edge cases first, then deterministic
     * general records for the rest of the dataset.
     *
     * @return array{
     *     hours_ago: int,
     *     is_open: bool,
     *     is_draft: bool,
     *     is_closed_without_merge: bool,
     *     review_mode: string,
     *     review_delay_hours: int,
     *     lifecycle_delay_hours: int,
     *     change_size: int
     * }
     */
    private function scenarioFor(int $index): array
    {
        $hoursAcrossSixteenWeeks = 16 * 7 * 24;

        $isDraft = $index % 17 === 0;
        $isOpen = $index % 5 === 0;

        $isClosedWithoutMerge =
            ! $isOpen && $index % 11 === 0;

        $reviewMode = match (true) {
            $isDraft => 'none',
            $index % 29 === 0 => 'bot',
            $index % 23 === 0 => 'dismissed',
            $index % 19 === 0 => 'pending',
            $index % 13 === 0 => 'self',
            $index % 7 === 0 => 'none',
            default => 'human',
        };

        $scenario = [
            'hours_ago' => 1 + (($index * 53) % $hoursAcrossSixteenWeeks),
            'is_open' => $isOpen,
            'is_draft' => $isDraft,
            'is_closed_without_merge' => $isClosedWithoutMerge,
            'review_mode' => $reviewMode,
            'review_delay_hours' => 1 + (($index * 7) % 72),
            'lifecycle_delay_hours' => 12 + (($index * 13) % 240),
            'change_size' => 20 + (($index * 83) % 900),
        ];

        /*
         * Explicit fixtures for important UI and metric cases.
         *
         * These include age boundaries, size boundaries, drafts,
         * self-review-only PRs, bot-only PRs and closed-unmerged PRs.
         */
        $overrides = [
            1 => [
                'hours_ago' => 2,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'none',
                'change_size' => 50,
            ],
            2 => [
                'hours_ago' => 24,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'human',
                'review_delay_hours' => 2,
                'change_size' => 51,
            ],
            3 => [
                'hours_ago' => 72,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'none',
                'change_size' => 200,
            ],
            4 => [
                'hours_ago' => 168,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'human',
                'review_delay_hours' => 8,
                'change_size' => 201,
            ],
            5 => [
                'hours_ago' => 192,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'none',
                'change_size' => 500,
            ],
            6 => [
                'hours_ago' => 240,
                'is_open' => true,
                'is_draft' => true,
                'is_closed_without_merge' => false,
                'review_mode' => 'none',
                'change_size' => 501,
            ],
            7 => [
                'hours_ago' => 48,
                'is_open' => false,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'human',
                'review_delay_hours' => 1,
                'lifecycle_delay_hours' => 6,
                'change_size' => 35,
            ],
            8 => [
                'hours_ago' => 96,
                'is_open' => false,
                'is_draft' => false,
                'is_closed_without_merge' => true,
                'review_mode' => 'human',
                'review_delay_hours' => 6,
                'lifecycle_delay_hours' => 20,
                'change_size' => 120,
            ],
            9 => [
                'hours_ago' => 120,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'self',
                'review_delay_hours' => 3,
                'change_size' => 310,
            ],
            10 => [
                'hours_ago' => 144,
                'is_open' => true,
                'is_draft' => false,
                'is_closed_without_merge' => false,
                'review_mode' => 'bot',
                'review_delay_hours' => 4,
                'change_size' => 620,
            ],
        ];

        return array_replace(
            $scenario,
            $overrides[$index] ?? []
        );
    }

    /**
     * @param  array<string, mixed>  $author
     * @param array{
     *     developers: array<int, array<string, mixed>>,
     *     bots: array<int, array<string, mixed>>
     * } $actors
     */
    private function seedReviewsForPullRequest(
        int $index,
        int $pullRequestId,
        array $author,
        array $actors,
        CarbonImmutable $createdAt,
        int $reviewDelayHours,
        string $mode
    ): int {
        if ($mode === 'none') {
            return 0;
        }

        $humanReviewer = $this->differentHumanReviewer(
            index: $index,
            authorId: $author['id'],
            developers: $actors['developers'],
        );

        $reviewer = match ($mode) {
            'self' => $author,
            'bot' => $actors['bots'][$index % count($actors['bots'])],
            default => $humanReviewer,
        };

        $submittedAt = $mode === 'pending'
            ? null
            : $createdAt->addHours($reviewDelayHours);

        if (
            $submittedAt !== null &&
            $submittedAt->greaterThan($this->anchor)
        ) {
            $submittedAt = $this->anchor;
        }

        $state = match ($mode) {
            'pending' => 'pending',
            'dismissed' => 'dismissed',
            'self' => 'approved',
            'bot' => 'approved',
            default => $index % 9 === 0
                ? 'changes_requested'
                : ($index % 4 === 0 ? 'commented' : 'approved'),
        };

        $this->insertReview(
            pullRequestId: $pullRequestId,
            reviewerId: $reviewer['id'],
            state: $state,
            submittedAt: $submittedAt,
            createdAt: $createdAt,
        );

        $count = 1;

        /*
         * Add a follow-up approval after some changes-requested reviews.
         * The first qualifying review remains the earlier review.
         */
        if (
            $mode === 'human' &&
            $state === 'changes_requested' &&
            $submittedAt !== null
        ) {
            $followUpAt = $submittedAt->addHours(6);

            if ($followUpAt->lessThanOrEqualTo($this->anchor)) {
                $this->insertReview(
                    pullRequestId: $pullRequestId,
                    reviewerId: $reviewer['id'],
                    state: 'approved',
                    submittedAt: $followUpAt,
                    createdAt: $followUpAt,
                );

                $count++;
            }
        }

        return $count;
    }

    private function insertReview(
        int $pullRequestId,
        int $reviewerId,
        string $state,
        ?CarbonImmutable $submittedAt,
        CarbonImmutable $createdAt
    ): void {
        $githubReviewId = ++$this->reviewSequence;

        $this->insertGetId('pull_request_reviews', [
            'pull_request_id' => $pullRequestId,
            'github_review_id' => $githubReviewId,
            'reviewer_github_user_id' => $reviewerId,
            'state' => $state,
            'submitted_at' => $submittedAt,
            'github_updated_at' => $submittedAt,
            'created_at' => $submittedAt ?? $createdAt,
            'updated_at' => $submittedAt ?? $createdAt,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $developers
     * @return array<string, mixed>
     */
    private function differentHumanReviewer(
        int $index,
        int $authorId,
        array $developers
    ): array {
        $startingIndex = (
            $index +
            $this->randomSeed
        ) % count($developers);

        for ($offset = 0; $offset < count($developers); $offset++) {
            $candidate = $developers[($startingIndex + $offset) % count($developers)];

            if ($candidate['id'] !== $authorId) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Unable to find a non-author reviewer.'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $repositories
     */
    private function seedSyncRuns(array $repositories): void
    {
        if (! Schema::hasTable('sync_runs')) {
            return;
        }

        foreach ($repositories as $index => $repository) {
            $completedAt =
                $repository['last_successful_sync_at'];

            $startedAt = $completedAt->subSeconds(
                16 + ($index * 4)
            );

            DB::table('sync_runs')->insert(
                $this->filterPayload('sync_runs', [
                    'repository_id' => $repository['id'],
                    'trigger_type' => 'scheduled',
                    'status' => 'successful',
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'cursor_before' => null,
                    'cursor_after' => $this->anchor->toIso8601String(),
                    'created_count' => 48,
                    'updated_count' => 0,
                    'unchanged_count' => 0,
                    'skipped_count' => 0,
                    'failed_count' => 0,
                    'rate_limit_remaining' => 4_800 - ($index * 50),
                    'rate_limit_reset_at' => $this->anchor->addHour(),
                    'error_category' => null,
                    'error_summary' => null,
                    'created_at' => $startedAt,
                    'updated_at' => $completedAt,
                ])
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $repositories
     */
    private function seedWebhookDemoData(int $organizationId, array $repositories): void
    {
        if (! Schema::hasTable('webhook_deliveries')) {
            return;
        }

        $repository = $repositories[0];

        // A delivery that failed once (transient) and succeeded on retry.
        $recoveredReceivedAt = $this->anchor->subHours(2);
        $recoveredId = $this->insertGetId('webhook_deliveries', [
            'organization_id' => $organizationId,
            'repository_id' => $repository['id'],
            'github_delivery_id' => 'demo-delivery-recovered-0001',
            'event_name' => 'pull_request',
            'action_name' => 'synchronize',
            'payload_sha256' => hash('sha256', 'demo-recovered-payload'),
            'payload_storage_mode' => 'metadata_only',
            'status' => 'processed',
            'received_at' => $recoveredReceivedAt,
            'queued_at' => $recoveredReceivedAt,
            'processed_at' => $recoveredReceivedAt->addSeconds(45),
            'created_at' => $recoveredReceivedAt,
            'updated_at' => $recoveredReceivedAt->addSeconds(45),
        ]);

        if (Schema::hasTable('webhook_processing_attempts')) {
            $this->insertGetId('webhook_processing_attempts', [
                'webhook_delivery_id' => $recoveredId,
                'attempt_number' => 1,
                'status' => 'failed',
                'started_at' => $recoveredReceivedAt,
                'completed_at' => $recoveredReceivedAt->addSeconds(5),
                'next_retry_at' => $recoveredReceivedAt->addSeconds(30),
                'error_category' => 'transient',
                'error_summary' => 'GitHub API request timed out.',
                'created_at' => $recoveredReceivedAt,
                'updated_at' => $recoveredReceivedAt->addSeconds(5),
            ]);
            $this->insertGetId('webhook_processing_attempts', [
                'webhook_delivery_id' => $recoveredId,
                'attempt_number' => 2,
                'status' => 'succeeded',
                'started_at' => $recoveredReceivedAt->addSeconds(30),
                'completed_at' => $recoveredReceivedAt->addSeconds(45),
                'next_retry_at' => null,
                'error_category' => null,
                'error_summary' => null,
                'created_at' => $recoveredReceivedAt->addSeconds(30),
                'updated_at' => $recoveredReceivedAt->addSeconds(45),
            ]);
        }

        // A delivery that permanently failed and is awaiting operator replay.
        $deadLetteredAt = $this->anchor->subHour();
        $deadLetteredId = $this->insertGetId('webhook_deliveries', [
            'organization_id' => $organizationId,
            'repository_id' => $repository['id'],
            'github_delivery_id' => 'demo-delivery-dead-lettered-0001',
            'event_name' => 'pull_request_review',
            'action_name' => 'submitted',
            'payload_sha256' => hash('sha256', 'demo-dead-lettered-payload'),
            'payload_storage_mode' => 'metadata_only',
            'status' => 'dead_lettered',
            'error_category' => 'validation',
            'error_summary' => 'The pull_request_review webhook payload is missing required fields.',
            'received_at' => $deadLetteredAt,
            'created_at' => $deadLetteredAt,
            'updated_at' => $deadLetteredAt,
        ]);

        if (Schema::hasTable('webhook_processing_attempts')) {
            $this->insertGetId('webhook_processing_attempts', [
                'webhook_delivery_id' => $deadLetteredId,
                'attempt_number' => 1,
                'status' => 'failed',
                'started_at' => $deadLetteredAt,
                'completed_at' => $deadLetteredAt,
                'next_retry_at' => null,
                'error_category' => 'validation',
                'error_summary' => 'The pull_request_review webhook payload is missing required fields.',
                'created_at' => $deadLetteredAt,
                'updated_at' => $deadLetteredAt,
            ]);
        }

        // A routine, successfully processed delivery.
        $processedAt = $this->anchor->subMinutes(20);
        $this->insertGetId('webhook_deliveries', [
            'organization_id' => $organizationId,
            'repository_id' => $repository['id'],
            'github_delivery_id' => 'demo-delivery-processed-0001',
            'event_name' => 'pull_request',
            'action_name' => 'opened',
            'payload_sha256' => hash('sha256', 'demo-processed-payload'),
            'payload_storage_mode' => 'metadata_only',
            'status' => 'processed',
            'received_at' => $processedAt,
            'queued_at' => $processedAt,
            'processed_at' => $processedAt->addSeconds(2),
            'created_at' => $processedAt,
            'updated_at' => $processedAt->addSeconds(2),
        ]);

        // Retag one already-seeded sync run as a reconciliation correction
        // rather than inserting a fifth row: V1BaselineSnapshotTest pins
        // sync_runs to exactly one row per demo repository.
        if (Schema::hasTable('sync_runs')) {
            DB::table('sync_runs')
                ->where('repository_id', $repository['id'])
                ->update([
                    'trigger_type' => 'reconciliation',
                    'updated_count' => 2,
                    'updated_at' => $this->anchor,
                ]);
        }
    }

    /**
     * V2.1 showcase scenarios (V2-FR-REL-018): a successful release, a
     * release awaiting approval, a failed deployment, and a deployment
     * that was rolled back after a successful rollout.
     *
     * @param  array<int, array<string, mixed>>  $repositories
     */
    private function seedReleaseDemoData(int $organizationId, array $repositories): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        $ownerUserId = DB::table('users')
            ->where('email', 'demo-owner@releaselens.invalid')
            ->value('id');
        $repositoryA = $repositories[0];
        $repositoryB = $repositories[1];

        $mergedPullRequestIds = function (int $repositoryId, int $limit) {
            return DB::table('pull_requests')
                ->where('repository_id', $repositoryId)
                ->whereNotNull('merged_at')
                ->orderByDesc('merged_at')
                ->limit($limit)
                ->pluck('id');
        };

        // Scenario 1: a successful, released release.
        $releasedAt = $this->anchor->subDays(3);
        $successfulReleaseId = $this->insertGetId('releases', [
            'organization_id' => $organizationId,
            'title' => 'v2.4.0 - Billing reliability improvements',
            'description' => 'Stabilizes invoice reconciliation and adds retry handling for the billing webhook pipeline.',
            'state' => 'released',
            'target_release_at' => $releasedAt->subDay(),
            'released_at' => $releasedAt,
            'created_by_user_id' => $ownerUserId,
            'created_at' => $releasedAt->subWeek(),
            'updated_at' => $releasedAt,
        ]);

        foreach ($mergedPullRequestIds($repositoryA['id'], 3) as $pullRequestId) {
            $this->insertGetId('release_pull_requests', [
                'release_id' => $successfulReleaseId,
                'pull_request_id' => $pullRequestId,
                'added_by_user_id' => $ownerUserId,
                'created_at' => $releasedAt->subWeek(),
                'updated_at' => $releasedAt->subWeek(),
            ]);
        }
        $this->insertGetId('release_repositories', [
            'release_id' => $successfulReleaseId,
            'repository_id' => $repositoryA['id'],
            'created_at' => $releasedAt->subWeek(),
            'updated_at' => $releasedAt->subWeek(),
        ]);
        $this->insertGetId('release_checklist_items', [
            'release_id' => $successfulReleaseId,
            'label' => 'Run smoke tests against staging',
            'is_required' => true,
            'position' => 0,
            'completed_at' => $releasedAt->subDay(),
            'completed_by_user_id' => $ownerUserId,
            'created_at' => $releasedAt->subWeek(),
            'updated_at' => $releasedAt->subDay(),
        ]);
        $this->insertGetId('release_approvals', [
            'release_id' => $successfulReleaseId,
            'approver_user_id' => $ownerUserId,
            'approval_generation' => 0,
            'approved_at' => $releasedAt->subDay(),
            'created_at' => $releasedAt->subDay(),
            'updated_at' => $releasedAt->subDay(),
        ]);
        $this->insertGetId('release_activities', [
            'release_id' => $successfulReleaseId,
            'actor_user_id' => $ownerUserId,
            'action' => 'state_changed',
            'metadata' => json_encode(['from' => 'approved', 'to' => 'released'], JSON_THROW_ON_ERROR),
            'occurred_at' => $releasedAt,
            'created_at' => $releasedAt,
            'updated_at' => $releasedAt,
        ]);

        // Scenario 2: a release awaiting approval.
        $inReviewAt = $this->anchor->subHours(6);
        $awaitingApprovalReleaseId = $this->insertGetId('releases', [
            'organization_id' => $organizationId,
            'title' => 'v2.5.0 - Mobile navigation refresh',
            'description' => 'Redesigns the mobile shell navigation and fixes deep-link handling.',
            'state' => 'in_review',
            'target_release_at' => $this->anchor->addDays(2),
            'created_by_user_id' => $ownerUserId,
            'created_at' => $inReviewAt->subDays(2),
            'updated_at' => $inReviewAt,
        ]);

        foreach ($mergedPullRequestIds($repositoryB['id'], 2) as $pullRequestId) {
            $this->insertGetId('release_pull_requests', [
                'release_id' => $awaitingApprovalReleaseId,
                'pull_request_id' => $pullRequestId,
                'added_by_user_id' => $ownerUserId,
                'created_at' => $inReviewAt->subDays(2),
                'updated_at' => $inReviewAt->subDays(2),
            ]);
        }
        $this->insertGetId('release_repositories', [
            'release_id' => $awaitingApprovalReleaseId,
            'repository_id' => $repositoryB['id'],
            'created_at' => $inReviewAt->subDays(2),
            'updated_at' => $inReviewAt->subDays(2),
        ]);
        $this->insertGetId('release_checklist_items', [
            'release_id' => $awaitingApprovalReleaseId,
            'label' => 'Confirm rollback plan documented',
            'is_required' => true,
            'position' => 0,
            'completed_at' => $inReviewAt,
            'completed_by_user_id' => $ownerUserId,
            'created_at' => $inReviewAt->subDays(2),
            'updated_at' => $inReviewAt,
        ]);
        $this->insertGetId('release_activities', [
            'release_id' => $awaitingApprovalReleaseId,
            'actor_user_id' => $ownerUserId,
            'action' => 'state_changed',
            'metadata' => json_encode(['from' => 'draft', 'to' => 'in_review'], JSON_THROW_ON_ERROR),
            'occurred_at' => $inReviewAt,
            'created_at' => $inReviewAt,
            'updated_at' => $inReviewAt,
        ]);

        if (! Schema::hasTable('deployments')) {
            return;
        }

        // Scenario 3: a failed deployment, unlinked, awaiting investigation.
        $failedAt = $this->anchor->subHours(4);
        $failedDeploymentId = $this->insertGetId('deployments', [
            'organization_id' => $organizationId,
            'repository_id' => $repositoryA['id'],
            'release_id' => null,
            'github_deployment_id' => 9_300_000_001,
            'ref' => 'main',
            'sha' => 'demo0001failed',
            'original_environment' => 'production',
            'normalized_environment' => 'production',
            'is_production' => true,
            'status' => 'failure',
            'original_status' => 'failure',
            'description' => 'Deploy '.$repositoryA['name'].' to production',
            'created_at_github' => $failedAt,
            'updated_at_github' => $failedAt,
            'created_at' => $failedAt,
            'updated_at' => $failedAt,
        ]);
        $this->insertGetId('deployment_status_events', [
            'deployment_id' => $failedDeploymentId,
            'status' => 'failure',
            'original_status' => 'failure',
            'description' => 'Health check failed after deploy.',
            'occurred_at' => $failedAt,
            'created_at' => $failedAt,
            'updated_at' => $failedAt,
        ]);

        // Scenario 4: a deployment that succeeded and was later rolled back.
        $rolledBackDeployedAt = $this->anchor->subDays(3)->addHour();
        $rolledBackDeploymentId = $this->insertGetId('deployments', [
            'organization_id' => $organizationId,
            'repository_id' => $repositoryA['id'],
            'release_id' => $successfulReleaseId,
            'github_deployment_id' => 9_300_000_002,
            'ref' => 'main',
            'sha' => 'demo0002rollback',
            'original_environment' => 'production',
            'normalized_environment' => 'production',
            'is_production' => true,
            'status' => 'inactive',
            'original_status' => 'inactive',
            'description' => 'Deploy '.$repositoryA['name'].' to production',
            'created_at_github' => $rolledBackDeployedAt,
            'updated_at_github' => $rolledBackDeployedAt->addHours(2),
            'created_at' => $rolledBackDeployedAt,
            'updated_at' => $rolledBackDeployedAt->addHours(2),
        ]);
        $this->insertGetId('deployment_status_events', [
            'deployment_id' => $rolledBackDeploymentId,
            'status' => 'success',
            'original_status' => 'success',
            'description' => 'Deploy finished.',
            'occurred_at' => $rolledBackDeployedAt,
            'created_at' => $rolledBackDeployedAt,
            'updated_at' => $rolledBackDeployedAt,
        ]);
        $this->insertGetId('deployment_status_events', [
            'deployment_id' => $rolledBackDeploymentId,
            'status' => 'inactive',
            'original_status' => 'inactive',
            'description' => 'Rolled back after elevated error rate.',
            'occurred_at' => $rolledBackDeployedAt->addHours(2),
            'created_at' => $rolledBackDeployedAt->addHours(2),
            'updated_at' => $rolledBackDeployedAt->addHours(2),
        ]);
    }

    private function pullRequestTitle(
        string $repositoryName,
        int $index
    ): string {
        $actions = [
            'Add',
            'Improve',
            'Refactor',
            'Fix',
            'Optimize',
            'Update',
            'Harden',
            'Simplify',
        ];

        $subjects = [
            'authentication flow',
            'invoice reconciliation',
            'dashboard filters',
            'repository synchronization',
            'error handling',
            'database query performance',
            'accessibility labels',
            'session validation',
            'pagination behavior',
            'mobile navigation',
            'audit logging',
            'API response mapping',
        ];

        $action = $actions[($index + $this->randomSeed) % count($actions)];

        $subject = $subjects[(($index * 3) + $this->randomSeed)
            % count($subjects)];

        return "{$action} {$subject} in {$repositoryName}";
    }

    /**
     * Insert a row and return its local primary key.
     *
     * Fields not found in the current migration are ignored, which
     * permits optional fields such as display_name or description.
     *
     * Required fields must still match the migration exactly.
     *
     * @param  array<string, mixed>  $payload
     */
    private function insertGetId(
        string $table,
        array $payload
    ): int {
        $filteredPayload = $this->filterPayload(
            $table,
            $payload
        );

        if ($filteredPayload === []) {
            throw new RuntimeException(
                "No compatible columns found for table [{$table}]."
            );
        }

        return (int) DB::table($table)
            ->insertGetId($filteredPayload);
    }

    /**
     * Remove optional payload keys that are not present in a table.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterPayload(
        string $table,
        array $payload
    ): array {
        $columns = $this->columnCache[$table]
            ??= Schema::getColumnListing($table);

        return array_intersect_key(
            $payload,
            array_flip($columns)
        );
    }
}
