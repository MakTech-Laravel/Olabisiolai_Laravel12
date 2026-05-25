<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AdminUserManagementSummaryTest extends TestCase
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

    public function test_admin_receives_user_management_summary_counts(): void
    {
        User::factory()->count(2)->create(['role' => 'user', 'email_verified_at' => now()]);
        User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $token = $admin->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/users/summary');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.summary.all_users', 4);
        $response->assertJsonPath('data.summary.total_users', 2);
        $response->assertJsonPath('data.summary.total_vendors', 1);
        $response->assertJsonPath('data.summary.total_admins', 1);
        $response->assertJsonPath('data.summary.new_signups', 4);
    }

    public function test_new_signups_only_includes_users_created_within_last_24_hours(): void
    {
        Carbon::setTestNow('2026-04-28 12:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'created_at' => now()->subDays(30),
        ]);

        User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
            'created_at' => Carbon::parse('2026-04-27 11:00:00'),
        ]);

        User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
            'created_at' => Carbon::parse('2026-04-28 11:00:00'),
        ]);

        $token = $admin->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/users/summary');

        $response->assertOk();
        $response->assertJsonPath('data.summary.all_users', 3);
        $response->assertJsonPath('data.summary.new_signups', 1);

        Carbon::setTestNow();
    }

    public function test_non_admin_cannot_access_user_management_summary(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)->getJson('/api/v1/admin/users/summary')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_user_management_summary(): void
    {
        $this->getJson('/api/v1/admin/users/summary')->assertUnauthorized();
    }
}
