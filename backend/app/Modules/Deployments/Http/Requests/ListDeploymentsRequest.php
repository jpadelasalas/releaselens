<?php

namespace App\Modules\Deployments\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class ListDeploymentsRequest extends FormRequest
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
            'environment' => ['sometimes', 'string'],
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
            'status' => $this->validated('status'),
            'environment' => $this->validated('environment'),
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page');
    }
}
