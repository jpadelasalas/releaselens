<?php

namespace App\Modules\Releases\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Releases\Enums\ReleaseApprovalMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReleasePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('org');

        return $this->user() !== null && Gate::forUser($this->user())->allows(
            OrganizationPolicy::MANAGE_RELEASES,
            $organizationId,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'approval_mode' => [
                'sometimes',
                Rule::in(array_map(fn (ReleaseApprovalMode $mode): string => $mode->value, ReleaseApprovalMode::cases())),
            ],
            'allow_self_approval' => ['sometimes', 'boolean'],
        ];
    }
}
