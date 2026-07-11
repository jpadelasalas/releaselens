<?php

namespace App\Modules\Incidents\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class ListIncidentsRequest extends FormRequest
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
            'state' => ['sometimes', 'string'],
            'severity' => ['sometimes', 'string'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'state' => $this->validated('state'),
            'severity' => $this->validated('severity'),
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page');
    }
}
