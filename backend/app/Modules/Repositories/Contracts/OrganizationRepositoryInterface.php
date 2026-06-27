<?php

namespace App\Modules\Repositories\Contracts;

use Illuminate\Support\Collection;

interface OrganizationRepositoryInterface
{
    /**
     * @return Collection<int, object>
     */
    public function listForOrganization(int $organizationId): Collection;
}
