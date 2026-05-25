<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\PrivateNotificationPushed;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\RealtimeNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

final class VendorNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_vendor_can_read_and_update_notification_settings(): void
    {
        $vendor = User::factory()->create([
            'role' => 'vendor',
            'wants_marketing_emails' => true,
            'settings' => ['notifications' => ['push' => true, 'whatsapp' => true]],
        ]);

        $token = $vendor->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/user/settings')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->patchJson('/api/v1/user/settings', [
                'settings' => [
                    'notifications' => [
                        'push' => false,
                        'whatsapp' => false,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.notifications.push', false)
            ->assertJsonPath('data.settings.notifications.whatsapp', false);

        $vendor->refresh();
        $this->assertFalse($vendor->wantsPushNotifications());
    }

    public function test_push_notifications_skipped_when_vendor_disables_push(): void
    {
        Notification::fake();
        Event::fake([PrivateNotificationPushed::class]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'settings' => ['notifications' => ['push' => false]],
        ]);

        app(RealtimeNotificationService::class)->newMessage(
            recipient: $vendor,
            senderId: 99,
            conversationUuid: 'conv-uuid',
            senderName: 'Buyer',
            preview: 'Hello',
            unreadCount: 1,
        );

        Notification::assertNothingSent();
        Event::assertNotDispatched(PrivateNotificationPushed::class);
    }

    public function test_push_notifications_delivered_when_enabled(): void
    {
        Notification::fake();
        Event::fake([PrivateNotificationPushed::class]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'settings' => ['notifications' => ['push' => true]],
        ]);

        app(RealtimeNotificationService::class)->newMessage(
            recipient: $vendor,
            senderId: 99,
            conversationUuid: 'conv-uuid',
            senderName: 'Buyer',
            preview: 'Hello',
            unreadCount: 1,
        );

        Notification::assertSentTo($vendor, RealtimeNotification::class);
        Event::assertDispatched(PrivateNotificationPushed::class);
    }
}
