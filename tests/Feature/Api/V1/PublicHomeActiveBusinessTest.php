<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomeActiveBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_unverified_business_appears_on_home_list_without_badge(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $unverified = BusinessInfo::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Free Active Shop',
            'business_status' => BusinessStatus::Active,
            'verification_status' => VerificationStatus::None,
            'sort_order' => 100,
        ]);

        BusinessInfo::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_status' => BusinessStatus::Inactive,
            'verification_status' => VerificationStatus::None,
        ]);

        $response = $this->getJson('/api/v1/businesses/home?per_page=50');

        $response->assertOk();

        $businesses = collect($response->json('data.businesses'));
        $payload = $businesses->firstWhere('id', $unverified->id);

        $this->assertNotNull($payload);
        $this->assertFalse($payload['shows_verified_badge']);
        $this->assertFalse($payload['is_verified']);
    }
}
