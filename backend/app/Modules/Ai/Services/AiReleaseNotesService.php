<?php

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Contracts\AiGenerationRepositoryInterface;
use App\Modules\Ai\Contracts\AiReleaseNotesProviderInterface;
use App\Modules\Ai\Enums\AiGenerationStatus;
use App\Modules\Ai\Exceptions\AiRuleException;
use App\Modules\Ai\Support\AiReleaseNotesFieldAllowlist;
use Illuminate\Support\Collection;
use Throwable;

class AiReleaseNotesService
{
    public function __construct(
        private readonly AiGenerationRepositoryInterface $generations,
        private readonly AiReleaseNotesProviderInterface $provider,
    ) {}

    public function generate(int $organizationId, object $release, Collection $pullRequests, ?int $actorUserId): object
    {
        $limit = (int) config('releaselens.ai.monthly_generation_limit');
        $used = $this->generations->countForOrganizationSince($organizationId, now()->startOfMonth());

        if ($used >= $limit) {
            throw new AiRuleException(
                'AI_GENERATION_LIMIT_EXCEEDED',
                "This organization has reached its monthly limit of {$limit} AI generations.",
                429,
            );
        }

        $context = AiReleaseNotesFieldAllowlist::extract($release, $pullRequests);

        try {
            $output = $this->provider->generate($context);
        } catch (Throwable $exception) {
            $this->generations->record(
                $organizationId,
                (int) $release->id,
                $actorUserId,
                $this->provider->name(),
                AiGenerationStatus::Failed->value,
                AiReleaseNotesFieldAllowlist::fields(),
                null,
                $exception->getMessage(),
            );

            throw new AiRuleException(
                'AI_GENERATION_FAILED',
                'Release notes generation failed. Please try again.',
                502,
            );
        }

        return $this->generations->record(
            $organizationId,
            (int) $release->id,
            $actorUserId,
            $this->provider->name(),
            AiGenerationStatus::Succeeded->value,
            AiReleaseNotesFieldAllowlist::fields(),
            $output,
            null,
        );
    }
}
