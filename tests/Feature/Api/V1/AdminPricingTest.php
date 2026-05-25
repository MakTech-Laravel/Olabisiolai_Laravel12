<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\PricingPackage;
use Database\Seeders\PricingPackageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed([
            RolePermissionSeeder::class,
            PricingPackageSeeder::class,
        ]);
    }

    public function test_admin_can_update_verification_pricing_dynamically(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $response = $this->postJson('/api/v1/admin/pricing/verification/update', [
            'packages' => [
                [
                    'package_key' => 'individual',
                    'title' => 'Individual',
                    'amount' => 3000,
                    'description' => 'Updated individual tier',
                    'perks' => ['Trusted badge'],
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'package_key' => 'business',
                    'title' => 'Business Name',
                    'amount' => 5500,
                    'description' => 'Updated business tier',
                    'perks' => ['Vendor priority'],
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'package_key' => 'ltd',
                    'title' => 'Limited Company (LTD)',
                    'amount' => 11000,
                    'description' => 'Updated LTD tier',
                    'perks' => ['Enterprise blue badge'],
                    'is_active' => true,
                    'sort_order' => 3,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertSame(3000, PricingPackage::query()
            ->where('package_key', 'individual')
            ->value('amount'));
    }
}
