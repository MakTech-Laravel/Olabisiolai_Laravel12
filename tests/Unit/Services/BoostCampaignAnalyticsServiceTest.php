<?php

namespace Tests\Unit\Services;

use App\Enums\BusinessStatus;
use App\Models\BusinessInfo;
use App\Models\BusinessProfileView;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Services\BoostCampaignAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoostCampaignAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_views_are_not_recorded(): void
    {
        [$vendor, $business] = $this->createVendorBusiness();
        $service = app(BoostCampaignAnalyticsService::class);

        $recorded = $service->recordProfileView($business, $vendor, '203.0.113.10');

        $this->assertFalse($recorded);
        $this->assertSame(0, BusinessProfileView::query()->count());
    }

    public function test_duplicate_views_in_session_are_not_recorded(): void
    {
        [$vendor, $business] = $this->createVendorBusiness();
        $viewer = User::factory()->create(['role' => 'user']);
        $service = app(BoostCampaignAnalyticsService::class);

        $this->assertTrue($service->recordProfileView($business, $viewer, '203.0.113.20'));
        $this->assertFalse($service->recordProfileView($business, $viewer, '203.0.113.20'));
        $this->assertSame(1, BusinessProfileView::query()->count());
    }

    public function test_guest_duplicate_ip_views_within_dedupe_window_are_not_recorded(): void
    {
        [, $business] = $this->createVendorBusiness();
        $service = app(BoostCampaignAnalyticsService::class);

        $this->assertTrue($service->recordProfileView($business, null, '203.0.113.30'));
        session()->forget('business_profile_views.recorded');
        $this->assertFalse($service->recordProfileView($business, null, '203.0.113.30'));
        $this->assertSame(1, BusinessProfileView::query()->count());
    }

    /**
     * @return array{0: User, 1: BusinessInfo}
     */
    private function createVendorBusiness(): array
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        return [$vendor, $business];
    }
}
