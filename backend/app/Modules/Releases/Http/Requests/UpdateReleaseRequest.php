<?php

namespace App\Modules\Releases\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateReleaseRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'target_release_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
