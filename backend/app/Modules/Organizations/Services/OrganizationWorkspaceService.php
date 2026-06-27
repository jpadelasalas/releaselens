<?php

namespace App\Modules\Organizations\Services;

use App\Models\User;
use App\Modules\Organizations\Contracts\OrganizationWorkspaceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationWorkspaceService
{
    public function __construct(
        private readonly OrganizationWorkspaceRepositoryInterface $organizations,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function listForUser(User $user): array
    {
        return $this->organizations->membershipsForUser($user->id)
            ->map(fn (object $membership): array => $this->membershipPayload($membership))
            ->all();
    }

    public function create(
        User $user,
        array $attributes,
        Request $request,
    ): object {
        $organization = $this->organizations->createWithOwner(
            $user->id,
            $attributes['name'],
            $this->uniqueSlug($attributes['name']),
            $attributes['timezone'],
        );

        $request->session()->put(
            'releaselens.active_organization_id',
            $organization->id,
        );

        return $organization;
    }

    public function activate(
        User $user,
        int $organizationId,
        Request $request,
    ): object {
        $membership = $this->organizations->membershipForUser(
            $organizationId,
            $user->id,
        );

        if ($membership === null) {
            throw (new ModelNotFoundException)->setModel(
                'Organization',
                [$organizationId],
            );
        }

        $request->session()->put(
            'releaselens.active_organization_id',
            $organizationId,
        );

        return $membership;
    }

    /** @return array<string, mixed> */
    public function membershipPayload(object $membership): array
    {
        return [
            'organization' => [
                'id' => (int) $membership->id,
                'name' => $membership->name,
                'slug' => $membership->slug,
                'timezone' => $membership->timezone,
            ],
            'role' => $membership->role,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'workspace';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->organizations->slugExists($slug)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
