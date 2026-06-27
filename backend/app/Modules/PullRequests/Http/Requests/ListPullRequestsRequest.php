<?php

namespace App\Modules\PullRequests\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPullRequestsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $anchor = CarbonImmutable::parse(
            config('releaselens.demo.anchor_date')
        )->utc();

        $this->merge([
            'date_from' => $this->input(
                'date_from',
                $anchor->subDays(29)->startOfDay()->toIso8601String(),
            ),
            'date_to' => $this->input(
                'date_to',
                $anchor->endOfDay()->toIso8601String(),
            ),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 25),
        ]);
    }

    public function authorize(): bool
    {
        $context = $this->session()->get('releaselens.context');

        return is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            (int) ($context['organization_id'] ?? 0) === (int) $this->route('org');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'repository_ids' => ['sometimes', 'array'],
            'repository_ids.*' => ['integer', 'min:1'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'review_status' => ['sometimes', Rule::in(['waiting'])],
            'attention' => ['sometimes', 'boolean'],
            'state' => ['sometimes', Rule::in(['closed_without_merge'])],
            'age_bucket' => [
                'sometimes',
                Rule::in(['under_1_day', '1_to_3_days', '3_to_7_days', 'over_7_days']),
            ],
            'size_bucket' => [
                'sometimes',
                Rule::in(['xs', 'small', 'medium', 'large']),
            ],
            'event' => ['sometimes', Rule::in(['opened', 'merged'])],
            'week' => ['required_with:event', 'date'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'repository_ids' => array_map(
                'intval',
                $validated['repository_ids'] ?? [],
            ),
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'review_status' => $validated['review_status'] ?? null,
            'attention' => (bool) ($validated['attention'] ?? false),
            'state' => $validated['state'] ?? null,
            'age_bucket' => $validated['age_bucket'] ?? null,
            'size_bucket' => $validated['size_bucket'] ?? null,
            'event' => $validated['event'] ?? null,
            'week' => $validated['week'] ?? null,
            'page' => (int) $validated['page'],
            'per_page' => (int) $validated['per_page'],
        ];
    }
}
