<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BoostPurchaseRequest;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessSearchHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_results_follow_five_tier_hierarchy(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create(['lga_name' => 'Hierarchy LGA']);

        $free = $this->createBusiness($category, $location, 'Free Vendor', premium: false, verified: false);
        $verifiedOnly = $this->createBusiness($category, $location, 'Verified Only', premium: false, verified: true);
        $premiumOnly = $this->createBusiness($category, $location, 'Premium Only', premium: true, verified: false);
        $premiumVerified = $this->createBusiness($category, $location, 'Premium Verified', premium: true, verified: true);
        $boosted = $this->createBusiness($category, $location, 'Premium Verified Boosted', premium: true, verified: true);

        $this->createActiveCampaign($boosted, $location);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame(
            [$boosted->id, $premiumVerified->id, $premiumOnly->id, $verifiedOnly->id, $free->id],
            array_values(array_filter(
                $ids,
                fn (int $id): bool => in_array($id, [
                    $boosted->id,
                    $premiumVerified->id,
                    $premiumOnly->id,
                    $verifiedOnly->id,
                    $free->id,
                ], true),
            )),
        );
    }

    public function test_unverified_premium_boost_campaign_does_not_enter_top_boosted_tier(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create(['lga_name' => 'Unverified Boost LGA']);

        $unverifiedBoosted = $this->createBusiness(
            $category,
            $location,
            'Unverified Boosted Premium',
            premium: true,
            verified: false,
        );
        $verifiedPremium = $this->createBusiness(
            $category,
            $location,
            'Verified Premium',
            premium: true,
            verified: true,
        );

        $this->createActiveCampaign($unverifiedBoosted, $location);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$verifiedPremium->id, $unverifiedBoosted->id], $ids);
    }

    public function test_proximity_orders_businesses_within_same_tier(): void
    {
        $category = Category::factory()->create();
        $anchorLat = 6.5244;
        $anchorLng = 3.3792;
        $location = Location::factory()->create([
            'latitude' => $anchorLat,
            'longitude' => $anchorLng,
        ]);

        $far = $this->createBusiness($category, $location, 'Far Premium Verified', premium: true, verified: true, lat: 6.60, lng: 3.50);
        $near = $this->createBusiness($category, $location, 'Near Premium Verified', premium: true, verified: true, lat: 6.5250, lng: 3.3800);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$near->id, $far->id], $ids);
    }

    private function createBusiness(
        Category $category,
        Location $location,
        string $name,
        bool $premium,
        bool $verified,
        ?float $lat = null,
        ?float $lng = null,
    ): BusinessInfo {
        $user = User::factory()->create(['role' => 'vendor']);

        $factory = BusinessInfo::factory()->for($user);
        if ($premium) {
            $factory = $factory->premiumActive();
        }

        return $factory->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => $name,
            'business_status' => BusinessStatus::Active,
            'verification_status' => $verified ? VerificationStatus::Approved : VerificationStatus::None,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    private function createActiveCampaign(BusinessInfo $business, Location $location): BoostPurchaseRequest
    {
        return BoostPurchaseRequest::query()->create([
            'user_id' => $business->user_id,
            'business_info_id' => $business->id,
            'location_id' => $location->id,
            'tier_key' => 'dynamic',
            'tier_label' => 'Dynamic Boost',
            'duration_days' => 7,
            'amount' => 3500,
            'currency' => 'NGN',
            'status' => BoostPurchaseRequestStatus::Approved,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
            'metadata' => ['daily_budget' => 500],
        ]);
    }
}
