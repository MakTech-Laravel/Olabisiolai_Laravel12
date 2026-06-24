<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\ReverbPingEvent;
use App\Enums\RealtimeNotificationType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Public realtime diagnostics used by the frontend /ws-test console to verify
 * the Backend -> Reverb -> Browser pipeline end to end. No authentication is
 * required — these endpoints intentionally expose only non-sensitive data.
 */
final class RealtimeController extends Controller
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotifications,
    ) {}
    /**
     * Dispatch a public diagnostic broadcast on the "reverb-ping" channel.
     */
    public function ping(Request $request): JsonResponse
    {
        $event = new ReverbPingEvent(
            message: (string) $request->query('message', 'Hello from Laravel Reverb 🚀'),
            triggeredAt: now()->toIso8601String(),
            source: 'api-public',
        );

        broadcast($event);

        return sendResponse(
            status: true,
            message: 'Event dispatched.',
            data: [
                'channel' => 'reverb-ping',
                'event' => 'ping',
                'payload' => $event->broadcastWith(),
            ],
            statusCode: HttpStatus::HTTP_OK,
        );
    }

    /**
     * Expose the effective broadcasting target so deployment issues (wrong
     * internal host/port/scheme) are diagnosable from the browser.
     */
    public function health(): JsonResponse
    {
        $connection = (string) config('broadcasting.default');
        $options = (array) config("broadcasting.connections.{$connection}.options", []);
        $key = (string) config("broadcasting.connections.{$connection}.key", '');

        return sendResponse(
            status: true,
            message: 'Broadcasting configuration.',
            data: [
                'broadcast_connection' => $connection,
                'host' => $options['host'] ?? null,
                'port' => $options['port'] ?? null,
                'scheme' => $options['scheme'] ?? null,
                'app_key_preview' => $key === '' ? null : substr($key, 0, 8).'...',
            ],
            statusCode: HttpStatus::HTTP_OK,
        );
    }

    /**
     * Dispatch a private diagnostic notification to the authenticated user.
     * Gated by REALTIME_ALLOW_TEST_BROADCAST (or local/testing env).
     */
    public function testBroadcast(Request $request): JsonResponse
    {
        abort_unless($this->testBroadcastRouteEnabled(), HttpStatus::HTTP_NOT_FOUND);

        $actor = $request->user('api') ?? $request->user('admin_api');
        if ($actor === null) {
            return sendResponse(false, 'Unauthenticated.', null, HttpStatus::HTTP_UNAUTHORIZED);
        }

        $title = (string) $request->input('title', 'WebSocket test notification');
        $message = (string) $request->input('message', 'Private channel diagnostic from /ws-test');

        if ($actor instanceof User) {
            $notification = RealtimeNotification::forUser(
                userId: (int) $actor->id,
                type: RealtimeNotificationType::SystemAnnouncement,
                title: $title,
                message: $message,
                data: ['source' => 'realtime_test_broadcast'],
            );
            $this->realtimeNotifications->notifyUser($actor, $notification);
            $channel = 'user.'.$actor->id;
        } elseif ($actor instanceof Admin) {
            $notification = RealtimeNotification::forAdmin(
                adminId: (int) $actor->id,
                type: RealtimeNotificationType::SystemAnnouncement,
                title: $title,
                message: $message,
                data: ['source' => 'realtime_test_broadcast'],
            );
            $this->realtimeNotifications->notifyAdmin($actor, $notification);
            $channel = 'admin.'.$actor->id;
        } else {
            return sendResponse(false, 'Unsupported actor.', null, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        }

        return sendResponse(
            status: true,
            message: 'Test notification dispatched.',
            data: [
                'channel' => $channel,
                'event' => 'app.notification',
            ],
            statusCode: HttpStatus::HTTP_CREATED,
        );
    }

    private function testBroadcastRouteEnabled(): bool
    {
        if (app()->isLocal() || app()->environment('testing', 'local')) {
            return true;
        }

        return filter_var(env('REALTIME_ALLOW_TEST_BROADCAST', false), FILTER_VALIDATE_BOOLEAN);
    }
}
