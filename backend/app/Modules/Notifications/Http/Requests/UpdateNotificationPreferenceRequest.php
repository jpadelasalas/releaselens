<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Support\NotificationRuleCatalog;
use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(NotificationRuleCatalog::knownTypes())],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
