<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RealtimeNotificationType;
use App\Events\ReverbPingEvent;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
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

    #[OA\Get(
        path: '/v1/realtime/ping',
        summary: 'Dispatch a public diagnostic broadcast on the "reverb-ping" channel',
        description: 'Public, unauthenticated. Rate-limited to 30 requests/minute.',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'message', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'Hello from Laravel Reverb 🚀')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event dispatched',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'channel', type: 'string', example: 'reverb-ping'),
                        new OA\Property(property: 'event', type: 'string', example: 'ping'),
                        new OA\Property(property: 'payload', type: 'object'),
                    ], type: 'object'),
                ]),
            ),
        ],
    )]
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

    #[OA\Get(
        path: '/v1/realtime/health',
        summary: 'Expose the effective broadcasting configuration for diagnostics',
        description: 'Public, unauthenticated. Lets deployment issues (wrong internal host/port/scheme) be diagnosed from the browser.',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Broadcasting configuration',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'broadcast_connection', type: 'string', example: 'reverb'),
                        new OA\Property(property: 'host', type: 'string', nullable: true),
                        new OA\Property(property: 'port', type: 'integer', nullable: true),
                        new OA\Property(property: 'scheme', type: 'string', nullable: true),
                        new OA\Property(property: 'app_key_preview', type: 'string', nullable: true, description: 'First 8 characters of the Reverb app key, followed by "...".'),
                    ], type: 'object'),
                ]),
            ),
        ],
    )]
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
