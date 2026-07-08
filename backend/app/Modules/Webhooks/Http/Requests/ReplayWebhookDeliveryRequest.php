<?php

namespace App\Modules\Webhooks\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReplayWebhookDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organizationId = (int) $this->route('org');

        return $this->user() !== null && Gate::forUser($this->user())->allows(
            OrganizationPolicy::REQUEST_SYNCHRONIZATION,
            $organizationId,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
