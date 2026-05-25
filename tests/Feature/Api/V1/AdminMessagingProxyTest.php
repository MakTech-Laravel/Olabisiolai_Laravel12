<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class AdminMessagingProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_list_and_read_messages_via_messaging_proxy(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $category = Category::factory()->create();
        $businessInfo = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
        ]);

        $start = $this->postJson('/api/v1/admin/business-info/message', [
            'business_info_id' => $businessInfo->id,
            'message' => 'Hello vendor from admin panel.',
        ]);

        $start->assertCreated();
        $conversationUuid = $start->json('data.conversation_uuid');
        $this->assertIsString($conversationUuid);

        $show = $this->getJson('/api/v1/admin/messaging/conversations/'.$conversationUuid);
        $show->assertOk();

        $messages = $this->getJson('/api/v1/admin/messaging/conversations/'.$conversationUuid.'/messages');
        $messages->assertOk();
        $messages->assertJsonPath('success', true);

        $send = $this->postJson('/api/v1/admin/messaging/conversations/'.$conversationUuid.'/messages', [
            'body' => 'Follow-up from admin messages page.',
        ]);

        $send->assertCreated();
    }
}
