<?php

namespace App\Modules\Webhooks\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class ShowWebhookDeliveryRequest extends FormRequest
{
    use AuthorizesOrganizationScope;

    public function authorize(): bool
    {
        return $this->organizationScopeAuthorized();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
