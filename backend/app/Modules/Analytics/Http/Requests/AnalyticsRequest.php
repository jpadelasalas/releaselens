<?php

namespace App\Modules\Analytics\Http\Requests;

use App\Modules\Shared\Http\Requests\AuthorizesOrganizationScope;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsRequest extends FormRequest
{
    use AuthorizesOrganizationScope;

    protected function prepareForValidation(): void
    {
        $anchor = $this->analyticsAnchor();

        $this->merge([
            'date_from' => $this->input(
                'date_from',
                $anchor->subDays(29)->startOfDay()->toIso8601String(),
            ),
            'date_to' => $this->input(
                'date_to',
                $anchor->endOfDay()->toIso8601String(),
            ),
        ]);
    }

    public function authorize(): bool
    {
        return $this->organizationScopeAuthorized();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'repository_ids' => ['sometimes', 'array'],
            'repository_ids.*' => ['integer', 'min:1'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array{
     *     repository_ids?: array<int, int>,
     *     date_from?: string,
     *     date_to?: string,
     *     now: string
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

        $filters['now'] = $this->analyticsAnchor()->toIso8601String();

        return $filters;
    }
}
