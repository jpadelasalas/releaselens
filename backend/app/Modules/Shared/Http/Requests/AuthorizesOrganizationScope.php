<?php

namespace App\Modules\Shared\Http\Requests;

use App\Modules\Organizations\Policies\OrganizationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

trait AuthorizesOrganizationScope
{
    protected function organizationScopeAuthorized(): bool
    {
        $context = $this->session()->get('releaselens.context');
        $organizationId = (int) $this->route('org');

        if (is_array($context) &&
            ($context['type'] ?? null) === 'demo' &&
            (int) ($context['organization_id'] ?? 0) === $organizationId) {
            return true;
        }

        return $this->user() !== null && Gate::forUser($this->user())->allows(
            OrganizationPolicy::VIEW,
            $organizationId,
        );
    }

    protected function analyticsAnchor(): CarbonImmutable
    {
        $context = $this->session()->get('releaselens.context');

        if (is_array($context) && ($context['type'] ?? null) === 'demo') {
            return CarbonImmutable::parse(
                config('releaselens.demo.anchor_date'),
            )->utc();
        }

        return CarbonImmutable::now('UTC');
    }
}
