<?php

namespace App\Modules\Repositories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ListRepositoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = $this->session()->get('releaselens.context');

        $isDemo = is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            (int) ($context['organization_id'] ?? 0) === (int) $this->route('org');

        if ($isDemo) {
            return true;
        }

        return $this->user() !== null && DB::table('organization_members')
            ->where('organization_id', (int) $this->route('org'))
            ->where('user_id', $this->user()->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
