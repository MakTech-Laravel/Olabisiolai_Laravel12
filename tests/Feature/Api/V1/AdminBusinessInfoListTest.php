<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AdminBusinessInfoListTest extends TestCase
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

    public function test_admin_can_list_business_profiles(): void
    {
        $category = Category::factory()->create(['name' => 'Cleaning']);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
            'name' => 'Vendor One',
            'email' => 'vendor1@example.com',
        ]);

        BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'business_name' => 'Sparkle Clean',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/business-info', [
            'search' => 'Sparkle',
            'per_page' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.count', 1);
        $response->assertJsonPath('data.business_profiles.0.business_name', 'Sparkle Clean');
        $response->assertJsonPath('data.business_profiles.0.vendor.email', 'vendor1@example.com');
        $response->assertJsonPath('data.business_profiles.0.category.name', 'Cleaning');
        $response->assertJsonStructure([
            'data' => [
                'summary' => ['total', 'pending_verification', 'approved_verification', 'free_plan', 'premium_plan'],
                'pagination' => ['current_page', 'per_page', 'last_page', 'total'],
            ],
        ]);
        $response->assertJsonPath('data.summary.total', 1);
    }

    public function test_admin_can_paginate_business_profiles(): void
    {
        $category = Category::factory()->create();

        foreach (range(1, 12) as $i) {
            $vendor = User::factory()->create([
                'role' => 'vendor',
                'email_verified_at' => now(),
            ]);
            BusinessInfo::factory()->create([
                'user_id' => $vendor->id,
                'category_id' => $category->id,
                'business_name' => 'Paged Business ' . $i,
            ]);
        }

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('test')->accessToken;

        $page1 = $this->withToken($token)->postJson('/api/v1/admin/business-info', [
            'per_page' => 5,
            'page' => 1,
        ]);
        $page1->assertOk();
        $page1->assertJsonPath('data.pagination.total', 12);
        $page1->assertJsonPath('data.pagination.last_page', 3);
        $page1->assertJsonPath('data.pagination.current_page', 1);
        $page1->assertJsonCount(5, 'data.business_profiles');

        $page2 = $this->withToken($token)->postJson('/api/v1/admin/business-info', [
            'per_page' => 5,
            'page' => 2,
        ]);
        $page2->assertOk();
        $page2->assertJsonPath('data.pagination.current_page', 2);
        $page2->assertJsonCount(5, 'data.business_profiles');
    }

    public function test_non_admin_cannot_list_business_profiles(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)->postJson('/api/v1/admin/business-info')
            ->assertUnauthorized();
    }

    public function test_admin_gets_empty_response_when_no_business_profile_exists(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $token = $admin->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/business-info');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.count', 0);
        $response->assertJsonPath('data.summary.total', 0);
        $response->assertJsonPath('data.pagination.total', 0);
        $response->assertJsonPath('data.pagination.current_page', 1);
    }
}
