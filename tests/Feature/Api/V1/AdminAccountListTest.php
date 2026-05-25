<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminAccountListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_supports_search_and_meta(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $super = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();

        Admin::query()->create([
            'first_name' => 'Alpha',
            'last_name' => 'One',
            'name' => 'Alpha One',
            'email' => 'alpha-find@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);

        Admin::query()->create([
            'first_name' => 'Beta',
            'last_name' => 'Two',
            'name' => 'Beta Two',
            'email' => 'beta-other@example.com',
            'password' => bcrypt('password'),
            'status' => AdminStatus::Active,
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($super, [], 'admin_api');

        $response = $this->getJson('/api/v1/admin/admins?search=alpha-find&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 10);

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertContains('alpha-find@example.com', $emails);
        $this->assertNotContains('beta-other@example.com', $emails);
    }
}
