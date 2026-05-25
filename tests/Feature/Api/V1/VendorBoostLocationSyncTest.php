<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\LgaBoost;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VendorBoostLocationSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingPackageSeeder::class);

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );
    }

    public function test_boost_checkout_updates_vendor_business_location_to_selected_lga(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $originalLocation = Location::factory()->create([
            'state_name' => 'Lagos',
            'lga_name' => 'Ikeja',
        ]);

        $boostLocation = Location::factory()->create([
            'state_name' => 'Jigawa',
            'lga_name' => 'Gabasawa',
        ]);

        LgaBoost::query()->create([
            'location_id' => $boostLocation->id,
            'enabled' => true,
            'tiers' => [
                [
                    'key' => 'top_1',
                    'label' => 'Top 1 Exclusive',
                    'total_slots' => 1,
                    'durations' => [
                        ['days' => 7, 'enabled' => true, 'price_amount' => 10000],
                    ],
                ],
            ],
            'durations' => [],
            'total_slots' => 1,
            'slots_sold' => 0,
            'slots_remaining' => 1,
            'active_boosts' => 0,
            'expired_boosts' => 0,
        ]);

        $business = BusinessInfo::factory()->for($user)->premiumActive()->create([
            'location_id' => $originalLocation->id,
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/boost/payment/init', [
            'tier_key' => 'top_1',
            'duration_days' => 7,
            'location_id' => $boostLocation->id,
        ]);

        $response->assertCreated();

        $business->refresh();
        $this->assertSame($boostLocation->id, $business->location_id);
    }
}
