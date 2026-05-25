<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BusinessStatus;
use App\Enums\ReviewReportStatus;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\BusinessReport;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BusinessReportTest extends TestCase
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

    public function test_public_can_fetch_business_report_reasons(): void
    {
        $response = $this->getJson('/api/v1/business-report-reasons');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.reasons.0.value', 'illegal_or_fraudulent');
        $response->assertJsonPath('data.reasons.0.label', 'This is illegal/fraudulent');
    }

    public function test_user_can_report_a_business(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $reporter = User::factory()->create(['role' => 'user']);

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        Passport::actingAs($reporter, [], 'api');

        $response = $this->postJson("/api/v1/user/businesses/{$business->id}/report", [
            'reason' => 'spam',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('business_reports', [
            'business_info_id' => $business->id,
            'user_id' => $reporter->id,
            'reason' => 'spam',
        ]);
    }

    public function test_admin_can_list_and_resolve_business_reports(): void
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $reporter = User::factory()->create(['role' => 'user']);
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'verification_status' => VerificationStatus::Approved,
            'business_status' => BusinessStatus::Active,
        ]);

        $report = BusinessReport::create([
            'business_info_id' => $business->id,
            'user_id' => $reporter->id,
            'reason' => 'spam',
            'description' => 'Looks like spam listings',
            'status' => ReviewReportStatus::Pending,
        ]);

        Passport::actingAs($admin, [], 'admin_api');

        $list = $this->getJson('/api/v1/admin/business-reports');
        $list->assertOk();
        $list->assertJsonPath('success', true);
        $list->assertJsonPath('data.0.id', $report->id);
        $list->assertJsonPath('data.0.business.business_name', $business->business_name);

        $resolve = $this->postJson("/api/v1/admin/business-reports/{$report->id}/resolve");
        $resolve->assertOk();
        $this->assertDatabaseHas('business_reports', [
            'id' => $report->id,
            'status' => ReviewReportStatus::Reviewed->value,
        ]);
    }
}
