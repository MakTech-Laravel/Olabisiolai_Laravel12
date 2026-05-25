<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminBusinessVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_send_message_and_change_business_status(): void
    {
        $category = Category::factory()->create(['name' => 'Cleaning']);
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $businessInfo = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'verification_status' => 'none',
            'business_status' => 'active',
        ]);

        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $messageResponse = $this->postJson('/api/v1/admin/business-info/message', [
            'business_info_id' => $businessInfo->id,
            'message' => 'Please keep your documents updated.',
        ]);

        $messageResponse->assertCreated();
        $conversationUuid = $messageResponse->json('data.conversation_uuid');
        $this->assertIsString($conversationUuid);
        $this->assertDatabaseHas('messages', [
            'body' => 'Please keep your documents updated.',
        ]);

        $statusResponse = $this->postJson('/api/v1/admin/business-info/status-change', [
            'business_info_id' => $businessInfo->id,
            'status' => 'inactive',
        ]);

        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('data.business.business_status', 'inactive');

        $viewResponse = $this->postJson('/api/v1/admin/business-info/view', [
            'business_info_id' => $businessInfo->id,
        ]);

        $viewResponse->assertOk();
        $viewResponse->assertJsonPath('data.business.verification_status', 'none');
        $viewResponse->assertJsonPath('data.business.business_status', 'inactive');
    }

    public function test_vendor_cannot_change_business_status(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => 'none',
            'business_status' => 'active',
        ]);

        $vendorToken = $vendor->createToken('vendor-test')->accessToken;

        $response = $this->withToken($vendorToken)->postJson('/api/v1/vendor/business/status-change', [
            'is_active' => false,
        ]);

        $response->assertNotFound();
    }

    public function test_non_admin_cannot_access_admin_business_endpoints(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $businessInfo = BusinessInfo::factory()->create(['user_id' => $vendor->id]);
        $token = $vendor->createToken('vendor-test')->accessToken;

        $this->withToken($token)->postJson('/api/v1/admin/business-info/status-change', [
            'business_info_id' => $businessInfo->id,
            'status' => 'inactive',
        ])->assertUnauthorized();
    }
}
