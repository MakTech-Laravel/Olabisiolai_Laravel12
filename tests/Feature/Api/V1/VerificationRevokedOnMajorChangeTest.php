<?php

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\PricingPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class VerificationRevokedOnMajorChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(PricingPackageSeeder::class);
    }

    public function test_changing_business_name_revokes_verification_badge(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $business = BusinessInfo::factory()->for($user)->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Old Shop Name',
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->put('/api/v1/vendor/business/update', [
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'New Shop Name',
            'business_description' => $business->business_description,
            'services' => $business->services_offered,
            'phone' => $business->phone,
        ]);

        $response->assertOk();

        $business->refresh();
        $this->assertSame(VerificationStatus::None, $business->verification_status);
        $this->assertNull($business->verified_at);
    }
}
