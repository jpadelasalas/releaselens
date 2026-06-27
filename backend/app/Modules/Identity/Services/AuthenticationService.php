<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function register(array $attributes, Request $request): User
    {
        $user = $this->users->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'normalized_email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'timezone' => $attributes['timezone'] ?? 'UTC',
        ]);

        $this->startSession($user, $request);

        return $user;
    }

    public function login(
        string $normalizedEmail,
        string $password,
        Request $request,
    ): ?User {
        $user = $this->users->findByNormalizedEmail($normalizedEmail);

        if (
            $user === null ||
            $user->disabled_at !== null ||
            ! Hash::check($password, $user->password)
        ) {
            return null;
        }

        $this->startSession($user, $request);

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /** @return array<string, mixed> */
    public function sessionPayload(User $user, Request $request): array
    {
        $memberships = $this->users->membershipsForUser($user->id)
            ->map(fn (object $membership): array => [
                'organization' => [
                    'id' => (int) $membership->id,
                    'name' => $membership->name,
                    'slug' => $membership->slug,
                    'timezone' => $membership->timezone,
                ],
                'role' => $membership->role,
            ])
            ->all();
        $activeOrganizationId = $request->session()->get('releaselens.active_organization_id');
        $hasActiveMembership = collect($memberships)->contains(
            fn (array $membership): bool => $membership['organization']['id'] === $activeOrganizationId,
        );

        return [
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
            ],
            'memberships' => $memberships,
            'active_organization_id' => $hasActiveMembership
                ? (int) $activeOrganizationId
                : null,
        ];
    }

    private function startSession(User $user, Request $request): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('releaselens.context');
    }
}
