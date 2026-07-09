<?php

namespace App\Modules\Releases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Releases\Contracts\ReleasePolicyRepositoryInterface;
use App\Modules\Releases\Enums\ReleaseApprovalMode;
use App\Modules\Releases\Http\Requests\ShowReleasePolicyRequest;
use App\Modules\Releases\Http\Requests\UpdateReleasePolicyRequest;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReleasePolicyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReleasePolicyRepositoryInterface $policies,
    ) {}

    public function show(ShowReleasePolicyRequest $request, int $org): JsonResponse
    {
        $policy = $this->policies->getForOrganization($org);

        return $this->successResponse(data: [
            'approval_mode' => $policy->approval_mode ?? ReleaseApprovalMode::SingleApprover->value,
            'allow_self_approval' => (bool) ($policy->allow_self_approval ?? false),
        ]);
    }

    public function update(UpdateReleasePolicyRequest $request, int $org): JsonResponse
    {
        $policy = $this->policies->upsertForOrganization($org, $request->validated());

        return $this->successResponse(data: [
            'approval_mode' => $policy->approval_mode,
            'allow_self_approval' => (bool) $policy->allow_self_approval,
        ]);
    }
}
