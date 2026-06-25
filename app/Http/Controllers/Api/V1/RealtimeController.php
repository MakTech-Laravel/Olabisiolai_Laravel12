<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\ReverbPingEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Public realtime diagnostics used by the frontend /ws-test console to verify
 * the Backend -> Reverb -> Browser pipeline end to end. No authentication is
 * required — these endpoints intentionally expose only non-sensitive data.
 */
class RealtimeController extends Controller
{
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
}
