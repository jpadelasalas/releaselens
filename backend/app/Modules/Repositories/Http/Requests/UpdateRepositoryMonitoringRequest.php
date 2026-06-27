<?php

namespace App\Modules\Repositories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepositoryMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['sync_enabled' => ['required', 'boolean']];
    }
}
