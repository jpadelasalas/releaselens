<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Http\Requests\ListNotificationsRequest;
use App\Modules\Notifications\Http\Requests\MarkAllNotificationsReadRequest;
use App\Modules\Notifications\Http\Requests\MarkNotificationReadRequest;
use App\Modules\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function index(ListNotificationsRequest $request, int $org): JsonResponse
    {
        $notifications = $this->notifications->forUser($org, $request->user()->id, $request->unreadOnly());

        return $this->successResponse(
            data: $notifications->map(fn (object $notification): array => $this->present($notification))->all(),
            meta: ['unread_count' => $this->notifications->unreadCount($org, $request->user()->id)],
        );
    }

    public function markRead(MarkNotificationReadRequest $request, int $org, int $notification): JsonResponse
    {
        $this->notifications->markRead($org, $request->user()->id, $notification);

        return $this->successResponse(data: ['status' => 'read']);
    }

    public function markAllRead(MarkAllNotificationsReadRequest $request, int $org): JsonResponse
    {
        $this->notifications->markAllRead($org, $request->user()->id);

        return $this->successResponse(data: ['status' => 'read']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'subject_type' => $notification->subject_type,
            'subject_id' => $notification->subject_id !== null ? (int) $notification->subject_id : null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
