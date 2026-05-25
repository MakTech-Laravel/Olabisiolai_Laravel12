<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSocialAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_show_returns_social_accounts_json(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'social_accounts' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/acme'],
                ['platform' => 'facebook', 'url' => 'https://facebook.com/acme'],
            ],
        ]);

        $response = $this->getJson("/api/v1/businesses/{$business->id}");

        $response->assertOk();
        $response->assertJsonPath('data.business.social_accounts.0.platform', 'instagram');
        $response->assertJsonPath('data.business.social_accounts.0.url', 'https://instagram.com/acme');
        $response->assertJsonPath('data.business.social_accounts.1.platform', 'facebook');
    }
}
