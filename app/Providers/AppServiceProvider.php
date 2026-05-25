<?php

namespace App\Providers;

use App\Contracts\Messaging\AttachmentScannerInterface;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserPresenceUpdated;
use App\Listeners\HandleMessageRead;
use App\Listeners\HandleMessageSent;
use App\Listeners\HandleUserPresence;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Services\Messaging\NoopAttachmentScanner;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\ClientRepository;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(AttachmentScannerInterface::class, NoopAttachmentScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->logIfReverbBroadcastingMisconfigured();

        Event::listen(MessageSent::class, HandleMessageSent::class);
        Event::listen(MessageRead::class, HandleMessageRead::class);
        Event::listen(UserPresenceUpdated::class, HandleUserPresence::class);

        once(function (): void {
            try {
                if (! Schema::hasTable('oauth_clients')) {
                    return;
                }
            } catch (\Throwable) {
                // Docker image build / CI: DB may be unset or unreachable; Passport runs after deploy + migrate.
                return;
            }

            $provider = config('auth.guards.api.provider');

            try {
                app(ClientRepository::class)->personalAccessClient($provider);
            } catch (RuntimeException $e) {
                if (! str_contains($e->getMessage(), 'Personal access client not found')) {
                    throw $e;
                }

                app(ClientRepository::class)->createPersonalAccessGrantClient(
                    config('app.name') . ' Personal Access Client',
                    $provider,
                );
            }
        });
    }

    /**
     * Empty REVERB_APP_* is a common Coolify mistake: WebSocket may appear fine while
     * Laravel cannot publish (BroadcastService swallows publish errors → no live messages).
     */
    private function logIfReverbBroadcastingMisconfigured(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }

        $conn = config('broadcasting.connections.reverb', []);
        $missing = collect(['app_id', 'key', 'secret'])
            ->filter(static fn (string $k): bool => ($conn[$k] ?? '') === '' || $conn[$k] === null);

        if ($missing->isEmpty()) {
            return;
        }

        Log::error('Reverb is enabled but REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET are incomplete. Real-time messaging will not work. Set all three in /var/www/.env (same REVERB_APP_KEY as VITE_REVERB_APP_KEY on the frontend), then `php artisan config:clear` and restart `laravel-reverb`. If credentials are set but peers still see no live events, try publishing via loopback: REVERB_BROADCAST_HOST=127.0.0.1 REVERB_BROADCAST_PORT=8089 REVERB_BROADCAST_SCHEME=http (see .env.production.example).', [
            'missing' => $missing->values()->all(),
        ]);
    }
}
