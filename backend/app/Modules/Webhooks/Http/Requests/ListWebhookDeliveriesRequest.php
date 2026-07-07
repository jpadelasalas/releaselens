<?php

namespace App\Modules\Webhooks\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class ListWebhookDeliveriesRequest extends FormRequest
{
    use AuthorizesOrganizationScope;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 25),
        ]);
    }

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
            'status' => ['sometimes', 'string'],
            'event_name' => ['sometimes', 'string'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? null,
            'event_name' => $validated['event_name'] ?? null,
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page');
    }
}
