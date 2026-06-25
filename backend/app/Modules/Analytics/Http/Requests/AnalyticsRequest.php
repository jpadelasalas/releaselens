<?php

namespace App\Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = $this->session()->get('releaselens.context');

        return is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            (int) ($context['organization_id'] ?? 0) === (int) $this->route('org');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'repository_ids' => ['sometimes', 'array'],
            'repository_ids.*' => ['integer', 'min:1'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array{
     *     repository_ids?: array<int, int>,
     *     date_from?: string,
     *     date_to?: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $filters = [];

        if (isset($validated['repository_ids'])) {
            $filters['repository_ids'] = array_map(
                'intval',
                $validated['repository_ids'],
            );
        }

        if (isset($validated['date_from'])) {
            $filters['date_from'] = $validated['date_from'];
        }

        if (isset($validated['date_to'])) {
            $filters['date_to'] = $validated['date_to'];
        }

        return $filters;
    }
}
