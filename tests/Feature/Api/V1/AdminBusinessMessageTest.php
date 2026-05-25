<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class AdminBusinessMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_message_stores_in_messages_table_and_vendor_can_use_same_conversation(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $category = Category::factory()->create();
        $businessInfo = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
        ]);

        $response = $this->postJson('/api/v1/admin/business-info/message', [
            'business_info_id' => $businessInfo->id,
            'message' => 'Please update your business profile.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $conversationUuid = $response->json('data.conversation_uuid');
        $this->assertIsString($conversationUuid);
        $this->assertNotSame('', $conversationUuid);

        $this->assertDatabaseHas('messages', [
            'body' => 'Please update your business profile.',
        ]);

        Passport::actingAs($vendor, [], 'api');

        $vendorAdminChat = $this->getJson('/api/v1/vendor/admin-chat');
        $vendorAdminChat->assertOk();
        $vendorAdminChat->assertJsonPath('data.conversation.uuid', $conversationUuid);

        $vendorConversations = $this->getJson('/api/v1/conversations');
        $vendorConversations->assertOk();
        $uuids = collect($vendorConversations->json('data'))->pluck('uuid')->filter()->all();
        $this->assertContains($conversationUuid, $uuids);
    }
}
