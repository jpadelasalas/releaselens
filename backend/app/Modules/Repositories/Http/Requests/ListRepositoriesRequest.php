<?php

namespace App\Modules\Repositories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRepositoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = $this->session()->get('releaselens.context');

        return is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            (int) ($context['organization_id'] ?? 0) === (int) $this->route('org');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
