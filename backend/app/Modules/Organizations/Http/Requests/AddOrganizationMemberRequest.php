<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AddOrganizationMemberRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }
}
