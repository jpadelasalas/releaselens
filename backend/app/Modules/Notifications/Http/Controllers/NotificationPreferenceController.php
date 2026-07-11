<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Contracts\NotificationPreferenceRepositoryInterface;
use App\Modules\Notifications\Http\Requests\ListNotificationPreferencesRequest;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Modules\Notifications\Support\NotificationRuleCatalog;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationPreferenceRepositoryInterface $preferences,
    ) {}

    public function index(ListNotificationPreferencesRequest $request, int $org): JsonResponse
    {
        $userId = $request->user()->id;

        $data = array_map(
            fn (string $type): array => [
                'type' => $type,
                'enabled' => $this->preferences->isEnabled($org, $userId, $type),
            ],
            NotificationRuleCatalog::knownTypes(),
        );

        return $this->successResponse(data: $data);
    }

    public function update(UpdateNotificationPreferenceRequest $request, int $org): JsonResponse
    {
        $preference = $this->preferences->setEnabled(
            $org,
            $request->user()->id,
            (string) $request->validated('type'),
            (bool) $request->validated('enabled'),
        );

        return $this->successResponse(data: [
            'type' => $preference->type,
            'enabled' => (bool) $preference->enabled,
        ]);
    }
}
