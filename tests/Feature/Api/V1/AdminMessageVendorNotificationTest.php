<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\PrivateNotificationPushed;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class AdminMessageVendorNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_vendor_receives_push_notification_when_admin_sends_message_and_push_enabled(): void
    {
        Notification::fake();
        Event::fake([PrivateNotificationPushed::class]);

        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
            'settings' => ['notifications' => ['push' => true]],
        ]);
        $category = Category::factory()->create();
        $businessInfo = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
        ]);

        $this->postJson('/api/v1/admin/business-info/message', [
            'business_info_id' => $businessInfo->id,
            'message' => 'Please review your listing.',
        ])->assertCreated();

        Notification::assertSentTo($vendor, RealtimeNotification::class, function (RealtimeNotification $notification): bool {
            $payload = $notification->toArray(new User);

            return ($payload['type'] ?? null) === 'new_message'
                && ($payload['from_platform_admin'] ?? false) === true
                && ($payload['action_url'] ?? '') === '/vendor/leads?channel=admin';
        });

        Event::assertDispatched(PrivateNotificationPushed::class);
    }

    public function test_vendor_does_not_receive_notification_when_push_disabled(): void
    {
        Notification::fake();
        Event::fake([PrivateNotificationPushed::class]);

        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
            'settings' => ['notifications' => ['push' => false]],
        ]);
        $category = Category::factory()->create();
        $businessInfo = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
        ]);

        $this->postJson('/api/v1/admin/business-info/message', [
            'business_info_id' => $businessInfo->id,
            'message' => 'Update required.',
        ])->assertCreated();

        Notification::assertNothingSent();
        Event::assertNotDispatched(PrivateNotificationPushed::class);
    }
}
