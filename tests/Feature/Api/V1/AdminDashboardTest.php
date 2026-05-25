<?php

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\BusinessInfo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
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

    public function test_admin_can_load_dashboard_metrics(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        BusinessInfo::factory()->count(2)->create([
            'verification_status' => VerificationStatus::Approved,
        ]);
        BusinessInfo::factory()->create([
            'verification_status' => VerificationStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard?range=weekly');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.range', 'weekly')
            ->assertJsonPath('data.stats.total_businesses', 3)
            ->assertJsonPath('data.stats.verified_businesses', 2)
            ->assertJsonPath('data.stats.pending_verifications', 1)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_businesses',
                        'verified_businesses',
                        'pending_verifications',
                        'daily_active_users',
                        'total_lead_clicks',
                    ],
                    'leads_over_time',
                    'new_businesses',
                    'quick_actions',
                ],
            ]);

        $this->assertCount(3, $response->json('data.quick_actions'));
        $this->assertCount(7, $response->json('data.leads_over_time'));
        $this->assertCount(7, $response->json('data.new_businesses'));
    }

    public function test_admin_can_load_sidebar_counts(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        BusinessInfo::factory()->count(2)->create([
            'verification_status' => VerificationStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/admin/sidebar-counts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_verifications', 2)
            ->assertJsonPath('data.pending_boosts', 0);
    }
}
