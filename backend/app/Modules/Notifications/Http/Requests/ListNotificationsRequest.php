<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
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
            'unread_only' => ['sometimes', 'boolean'],
        ];
    }

    public function unreadOnly(): bool
    {
        return (bool) $this->boolean('unread_only');
    }
}
