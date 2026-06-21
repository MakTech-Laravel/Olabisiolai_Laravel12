<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\ConversationType;
use App\Enums\ParticipantRole;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ConversationPeerBusinessInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_conversation_peer_includes_vendor_business_info_id(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $customer = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendorUser->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Sparkle Clean Services',
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $conversation = Conversation::factory()->create([
            'type' => ConversationType::Direct,
            'created_by' => $customer->id,
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $customer->id,
            'role' => ParticipantRole::Admin,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $vendorUser->id,
            'role' => ParticipantRole::Member,
        ]);

        Passport::actingAs($customer, guard: 'api');

        $response = $this->getJson('/api/v1/conversations/' . $conversation->uuid);

        $response->assertOk();
        $response->assertJsonPath('data.peer.business_info_id', $business->id);
        $response->assertJsonPath('data.peer.role', 'vendor');
        $response->assertJsonPath('data.display_name', 'Sparkle Clean Services');
    }

    public function test_vendor_inbox_shows_customer_personal_name(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $customer = User::factory()->create([
            'role' => 'user',
            'name' => 'Ola Ola',
            'email_verified_at' => now(),
        ]);

        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendorUser->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Nourishfoodmart',
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $conversation = Conversation::factory()->create([
            'type' => ConversationType::Direct,
            'created_by' => $customer->id,
            'business_info_id' => $business->id,
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $customer->id,
            'role' => ParticipantRole::Admin,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $vendorUser->id,
            'role' => ParticipantRole::Member,
        ]);

        Passport::actingAs($vendorUser, guard: 'api');

        $response = $this->getJson('/api/v1/conversations/' . $conversation->uuid);

        $response->assertOk();
        $response->assertJsonPath('data.display_name', 'Ola Ola');
        $response->assertJsonPath('data.peer.personal_name', 'Ola Ola');
    }

    public function test_vendor_with_business_page_can_initiate_direct_message(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $sender = User::factory()->create([
            'role' => 'vendor',
            'name' => 'Ola Ola',
            'email_verified_at' => now(),
        ]);

        BusinessInfo::factory()->create([
            'user_id' => $sender->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Ola Foods',
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $targetVendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $targetBusiness = BusinessInfo::factory()->create([
            'user_id' => $targetVendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Nourishfoodmart',
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        Passport::actingAs($sender, guard: 'api');

        $create = $this->postJson('/api/v1/conversations', [
            'type' => ConversationType::Direct->value,
            'participants' => [$targetVendor->uuid],
            'business_info_id' => $targetBusiness->id,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.display_name', 'Nourishfoodmart');
    }
}
