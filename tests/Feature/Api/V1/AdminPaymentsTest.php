<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentPurpose;
use App\Models\Admin;
use App\Models\Payment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminPaymentsTest extends TestCase
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

    public function test_admin_can_list_payments_and_view_analytics(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        Payment::factory()->completed()->create([
            'purpose' => PaymentPurpose::Subscription,
            'amount' => 5000,
        ]);
        Payment::factory()->create([
            'purpose' => PaymentPurpose::Boost,
            'amount' => 3000,
            'status' => \App\Enums\PaymentStatus::Pending,
        ]);

        $list = $this->getJson('/api/v1/admin/payments?per_page=10');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, count($list->json('data.items')));

        $analytics = $this->getJson('/api/v1/admin/payments/analytics?trend_range=monthly');
        $analytics->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'total_revenue',
                        'verification_revenue',
                        'subscription_revenue',
                        'boost_revenue',
                    ],
                    'trend',
                    'breakdown',
                ],
            ]);

        $this->assertSame(5000.0, (float) $analytics->json('data.overview.subscription_revenue'));
    }

    public function test_admin_can_filter_payments_by_purpose_and_status(): void
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        Payment::factory()->completed()->create(['purpose' => PaymentPurpose::Verification]);
        Payment::factory()->create(['purpose' => PaymentPurpose::Boost, 'status' => \App\Enums\PaymentStatus::Pending]);

        $response = $this->getJson('/api/v1/admin/payments?purpose=boost&status=pending');
        $response->assertOk();

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('boost', $items[0]['transaction_type']);
        $this->assertSame('pending', $items[0]['status']);
    }
}
