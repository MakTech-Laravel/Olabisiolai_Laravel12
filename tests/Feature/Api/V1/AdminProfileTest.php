<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminProfileTest extends TestCase
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

    public function test_admin_can_view_and_update_profile(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $this->getJson('/api/v1/admin/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin.email', 'superadmin@dev.com');

        $this->putJson('/api/v1/admin/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Admin',
            'email' => 'updated-admin@dev.com',
            'phone' => '+2348001112222',
        ])
            ->assertOk()
            ->assertJsonPath('data.admin.first_name', 'Updated')
            ->assertJsonPath('data.admin.email', 'updated-admin@dev.com')
            ->assertJsonPath('data.admin.phone', '+2348001112222');
    }

    public function test_admin_can_manage_two_factor_status(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $this->getJson('/api/v1/admin/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }
}
