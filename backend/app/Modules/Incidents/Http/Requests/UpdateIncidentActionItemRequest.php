<?php

namespace App\Modules\Incidents\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateIncidentActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('org');

        return $this->user() !== null && Gate::forUser($this->user())->allows(
            OrganizationPolicy::MANAGE_INCIDENTS,
            $organizationId,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'completed' => ['required', 'boolean'],
        ];
    }
}
