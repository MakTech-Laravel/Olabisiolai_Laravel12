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

class PublicBusinessBoostPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_lists_boosted_businesses_before_others_by_tier_in_lga(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create([
            'lga_name' => 'Test LGA',
            'city_name' => 'Test City',
        ]);

        $plain = $this->createActiveBusiness($category, $location, 'Plain Vendor', 10);
        $top10 = $this->createActiveBusiness($category, $location, 'Bronze Boost', 20, premium: true, verified: true);
        $top5 = $this->createActiveBusiness($category, $location, 'Silver Boost', 30, premium: true, verified: true);
        $top1 = $this->createActiveBusiness($category, $location, 'Gold Boost', 40, premium: true, verified: true);

        $this->createActiveCampaign($top10, $location, 'top_10');
        $this->createActiveCampaign($top5, $location, 'top_5');
        $this->createActiveCampaign($top1, $location, 'top_1');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame(
            [$top1->id, $top5->id, $top10->id, $plain->id],
            array_values(array_filter($ids, fn (int $id): bool => in_array($id, [$top1->id, $top5->id, $top10->id, $plain->id], true))),
        );

        $this->assertSame('top_1', collect($response->json('data.businesses'))->firstWhere('id', $top1->id)['active_boost_tier'] ?? null);
    }

    public function test_boost_in_other_lga_does_not_rank_when_filtering_target_lga(): void
    {
        $category = Category::factory()->create();
        $ikeja = Location::factory()->create(['lga_name' => 'Ikeja']);
        $lekki = Location::factory()->create(['lga_name' => 'Lekki']);

        $inIkeja = $this->createActiveBusiness($category, $ikeja, 'Ikeja Plain', 1);
        $inLekkiBoosted = $this->createActiveBusiness($category, $lekki, 'Lekki Boosted', 50, premium: true, verified: true);

        $this->createActiveCampaign($inLekkiBoosted, $lekki, 'top_1');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$ikeja->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$inIkeja->id], $ids);
        $this->assertNotContains($inLekkiBoosted->id, $ids);
    }

    public function test_exact_lga_name_search_without_location_id_orders_by_boost_tier(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create(['lga_name' => 'Gabasawa']);

        $plain = $this->createActiveBusiness($category, $location, 'Plain Shop', 1);
        $top1 = $this->createActiveBusiness($category, $location, 'Top Shop', 2, premium: true, verified: true);

        $this->createActiveCampaign($top1, $location, 'top_1');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&search=Gabasawa');

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$top1->id, $plain->id], $ids);
    }

    public function test_search_endpoint_prioritises_active_boost_campaigns(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $other = $this->createActiveBusiness($category, $location, 'Other Shop', 5);
        $boosted = $this->createActiveBusiness($category, $location, 'Boosted Shop', 1, premium: true, verified: true);

        $this->createActiveCampaign($boosted, $location, 'top_1');

        $response = $this->getJson('/api/v1/businesses/search?query=Shop&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$boosted->id, $other->id], $ids);
    }

    public function test_higher_daily_boost_budget_ranks_first_within_boosted_tier(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create([
            'latitude' => 6.5244,
            'longitude' => 3.3792,
        ]);

        $lowerBudget = $this->createActiveBusiness($category, $location, 'Lower Budget Boost', 1, premium: true, verified: true);
        $higherBudget = $this->createActiveBusiness($category, $location, 'Higher Budget Boost', 2, premium: true, verified: true);

        $this->createActiveCampaign($lowerBudget, $location, 'dynamic', dailyBudget: 600);
        $this->createActiveCampaign($higherBudget, $location, 'dynamic', dailyBudget: 1200);

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id='.$location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$higherBudget->id, $lowerBudget->id], $ids);
    }

    private function createActiveBusiness(
        Category $category,
        Location $location,
        string $name,
        int $sortOrder,
        bool $premium = false,
        bool $verified = false,
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
            'sort_order' => $sortOrder,
            'verification_status' => $verified ? VerificationStatus::Approved : VerificationStatus::None,
        ]);
    }

    private function createActiveCampaign(
        BusinessInfo $business,
        Location $location,
        string $tierKey,
        ?float $dailyBudget = null,
    ): BoostPurchaseRequest {
        $durationDays = 7;
        $dailyBudget ??= 500;
        $amount = $dailyBudget * $durationDays;

        return BoostPurchaseRequest::query()->create([
            'user_id' => $business->user_id,
            'business_info_id' => $business->id,
            'location_id' => $location->id,
            'tier_key' => $tierKey,
            'tier_label' => strtoupper(str_replace('_', ' ', $tierKey)),
            'duration_days' => $durationDays,
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => BoostPurchaseRequestStatus::Approved,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
            'metadata' => $dailyBudget !== null ? ['daily_budget' => $dailyBudget] : null,
        ]);
    }
}
