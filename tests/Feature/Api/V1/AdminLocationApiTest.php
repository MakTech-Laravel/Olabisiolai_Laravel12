<?php

namespace Tests\Feature\Api\V1;

use App\Models\Lga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AdminLocationApiTest extends TestCase
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

    public function test_admin_can_store_location_from_map_pick_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $token = $admin->createToken('test')->accessToken;

        $payload = [
            'country' => [
                'name' => 'Nigeria',
                'iso_code' => 'ng',
            ],
            'state' => [
                'name' => 'Lagos',
            ],
            'lga' => [
                'name' => 'Ikeja',
                'latitude' => 6.5952,
                'longitude' => 3.3375,
            ],
            'map_pick' => [
                'placeId' => 'test-place-id',
                'resourceName' => 'places/test-place-id',
                'viewport' => [
                    'north' => 6.70,
                    'south' => 6.50,
                    'east' => 3.40,
                    'west' => 3.20,
                ],
                'addressComponents' => [
                    ['longText' => 'Ikeja', 'types' => ['locality']],
                ],
            ],
        ];

        $response = $this->withToken($token)->postJson('/api/v1/admin/locations/store', $payload);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.country.iso_code', 'NG');
        $response->assertJsonPath('data.lga.google_place_id', 'test-place-id');
    }

    public function test_admin_can_get_lga_vendor_coordinates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $token = $admin->createToken('test')->accessToken;

        $this->withToken($token)->postJson('/api/v1/admin/locations/store', [
            'country' => ['name' => 'Nigeria', 'iso_code' => 'NG'],
            'state' => ['name' => 'Oyo'],
            'lga' => ['name' => 'Ibadan North', 'latitude' => 7.40, 'longitude' => 3.90],
        ])->assertCreated();

        $lga = Lga::query()->where('name', 'Ibadan North')->firstOrFail();

        $this->withToken($token)->postJson("/api/v1/admin/lgas/{$lga->id}/vendors/sync", [
            'vendors' => [
                ['vendor_id' => $vendor->id, 'lat' => 7.401, 'lng' => 3.905],
            ],
        ])->assertOk();

        $response = $this->withToken($token)->getJson("/api/v1/admin/lgas/{$lga->id}/vendors");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.vendors.0.vendor_id', $vendor->id);
        $response->assertJsonPath('data.vendors.0.lat', '7.4010000');
        $response->assertJsonPath('data.vendors.0.lng', '3.9050000');
    }
}
