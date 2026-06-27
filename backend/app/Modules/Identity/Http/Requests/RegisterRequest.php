<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'timezone' => $this->input('timezone', 'UTC'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                'unique:users,normalized_email',
            ],
            'password' => [
                'required', 'confirmed',
                Password::min(12)->letters()->numbers(),
            ],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
