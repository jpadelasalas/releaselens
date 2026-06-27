<?php

namespace App\Modules\Repositories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRepositoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'repository_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'repository_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
