<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\ReviewReportStatus;
use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReviewReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_public_can_fetch_review_report_reasons(): void
    {
        $response = $this->getJson('/api/v1/review-report-reasons');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.reasons.0.value', 'spam');
        $response->assertJsonPath('data.reasons.0.label', 'This is spam');
        $response->assertJsonFragment(['value' => 'inappropriate']);
        $response->assertJsonFragment(['value' => 'not_relevant']);
        $response->assertJsonFragment(['value' => 'conflict_of_interest']);
        $response->assertJsonFragment(['value' => 'other']);
    }

    public function test_user_can_report_a_review_with_flutter_reason_keys(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $reviewer = User::factory()->create(['role' => 'user']);
        $reporter = User::factory()->create(['role' => 'user']);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
        ]);

        $review = Review::factory()
            ->forUser($reviewer)
            ->forBusiness($business)
            ->create();

        Passport::actingAs($reporter, [], 'api');

        $response = $this->postJson("/api/v1/user/reviews/{$review->id}/report", [
            'reason' => 'inappropriate',
            'description' => 'Contains offensive language.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('review_reports', [
            'review_id' => $review->id,
            'user_id' => $reporter->id,
            'reason' => 'inappropriate',
            'status' => ReviewReportStatus::Pending->value,
        ]);
    }

    public function test_review_report_rejects_business_only_reason(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $reviewer = User::factory()->create(['role' => 'user']);
        $reporter = User::factory()->create(['role' => 'user']);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
        ]);

        $review = Review::factory()
            ->forUser($reviewer)
            ->forBusiness($business)
            ->create();

        Passport::actingAs($reporter, [], 'api');

        $response = $this->postJson("/api/v1/user/reviews/{$review->id}/report", [
            'reason' => 'wrong_price',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('review_reports', 0);
    }
}
