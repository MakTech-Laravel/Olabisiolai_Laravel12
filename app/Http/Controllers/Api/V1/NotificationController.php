<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $onlyUnread = $request->boolean('unread_only');

        $query = $user->notifications()->latest();

        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate((int) $request->integer('per_page', 20));

        return sendResponse(true, 'Notifications retrieved.', [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications->getCollection()->map(static fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        return sendResponse(true, 'Unread count retrieved.', [
            'count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $notification = $user->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return sendResponse(false, 'Notification not found.', null, Response::HTTP_NOT_FOUND);
        }

        $notification->markAsRead();

        return sendResponse(true, 'Notification marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $user->unreadNotifications->markAsRead();

        return sendResponse(true, 'All notifications marked as read.');
    }

    public function markBulkRead(Request $request)
    {
        $user = $request->user('api') ?? $request->user('admin_api');

        if ($user === null) {
            return sendResponse(false, 'Unauthenticated.', null, Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $user->unreadNotifications()
            ->whereIn('id', $validated['ids'])
            ->update(['read_at' => now()]);

        return sendResponse(true, 'Notifications marked as read.');
    }
}
