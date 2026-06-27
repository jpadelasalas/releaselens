<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;

class CreateOrganizationRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'timezone' => $this->input('timezone', 'UTC'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
