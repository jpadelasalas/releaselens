<?php

namespace App\Modules\Deployments\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpsertEnvironmentMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('org');

        return $this->user() !== null && Gate::forUser($this->user())->allows(
            OrganizationPolicy::MANAGE_REPOSITORIES,
            $organizationId,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_environment' => ['required', 'string', 'max:255'],
            'normalized_environment' => ['required', 'string', 'max:32'],
            'is_production' => ['required', 'boolean'],
        ];
    }
}
