<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BoostPurchaseRequestStatus;
use App\Enums\BusinessStatus;
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
        $top10 = $this->createActiveBusiness($category, $location, 'Bronze Boost', 20);
        $top5 = $this->createActiveBusiness($category, $location, 'Silver Boost', 30);
        $top1 = $this->createActiveBusiness($category, $location, 'Gold Boost', 40);

        $this->createActiveCampaign($top10, $location, 'top_10');
        $this->createActiveCampaign($top5, $location, 'top_5');
        $this->createActiveCampaign($top1, $location, 'top_1');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id=' . $location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame(
            [$top1->id, $top5->id, $top10->id, $plain->id],
            array_values(array_filter($ids, fn(int $id): bool => in_array($id, [$top1->id, $top5->id, $top10->id, $plain->id], true))),
        );

        $this->assertSame('top_1', collect($response->json('data.businesses'))->firstWhere('id', $top1->id)['active_boost_tier'] ?? null);
    }

    public function test_boost_in_other_lga_does_not_rank_when_filtering_target_lga(): void
    {
        $category = Category::factory()->create();
        $ikeja = Location::factory()->create(['lga_name' => 'Ikeja']);
        $lekki = Location::factory()->create(['lga_name' => 'Lekki']);

        $inIkeja = $this->createActiveBusiness($category, $ikeja, 'Ikeja Plain', 1);
        $inLekkiBoosted = $this->createActiveBusiness($category, $lekki, 'Lekki Boosted', 50);

        $this->createActiveCampaign($inLekkiBoosted, $lekki, 'top_1');

        $response = $this->getJson('/api/v1/businesses/home?per_page=20&location_id=' . $ikeja->id);

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
        $top1 = $this->createActiveBusiness($category, $location, 'Top Shop', 2);

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
        $boosted = $this->createActiveBusiness($category, $location, 'Boosted Shop', 1);

        $this->createActiveCampaign($boosted, $location, 'top_1');

        $response = $this->getJson('/api/v1/businesses/search?query=Shop&location_id=' . $location->id);

        $response->assertOk();

        $ids = collect($response->json('data.businesses'))->pluck('id')->all();

        $this->assertSame([$boosted->id, $other->id], $ids);
    }

    private function createActiveBusiness(Category $category, Location $location, string $name, int $sortOrder): BusinessInfo
    {
        $user = User::factory()->create(['role' => 'vendor']);

        return BusinessInfo::factory()->for($user)->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => $name,
            'business_status' => BusinessStatus::Active,
            'sort_order' => $sortOrder,
        ]);
    }

    private function createActiveCampaign(BusinessInfo $business, Location $location, string $tierKey): BoostPurchaseRequest
    {
        return BoostPurchaseRequest::query()->create([
            'user_id' => $business->user_id,
            'business_info_id' => $business->id,
            'location_id' => $location->id,
            'tier_key' => $tierKey,
            'tier_label' => strtoupper(str_replace('_', ' ', $tierKey)),
            'duration_days' => 7,
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => BoostPurchaseRequestStatus::Approved,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
        ]);
    }
}
