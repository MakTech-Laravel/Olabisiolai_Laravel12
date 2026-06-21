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
}
