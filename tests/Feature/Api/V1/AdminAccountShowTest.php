<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminAccountShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_single_admin_details(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        $target = Admin::query()->create([
            'first_name' => 'Show',
            'last_name' => 'Me',
            'name' => 'Show Me',
            'email' => 'show-me@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);
        $target->assignRole('editor-unit');

        Passport::actingAs($super, [], 'admin_api');

        $this->getJson("/api/v1/admin/admins/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.email', 'show-me@example.com')
            ->assertJsonStructure([
                'data' => [
                    'roles',
                    'permissions',
                    'is_super_admin',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_404_for_missing_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($super, [], 'admin_api');

        $this->getJson('/api/v1/admin/admins/999999')
            ->assertNotFound();
    }
}
