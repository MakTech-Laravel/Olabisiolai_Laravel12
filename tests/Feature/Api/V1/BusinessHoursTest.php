<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
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

    public function test_create_business_persists_custom_hours(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $location = Location::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/business/create', [
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Hours Test Co',
            'business_description' => 'We are open on custom hours.',
            'services' => ['Consulting'],
            'phone' => '+2348012345678',
            'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            'cover_photos' => [UploadedFile::fake()->image('cover.png', 200, 200)],
            'business_hours' => [
                ['day' => 'monday', 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 'tuesday', 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 'wednesday', 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 'thursday', 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 'friday', 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ['day' => 'saturday', 'is_closed' => true],
                ['day' => 'sunday', 'is_closed' => true],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.business.business_hours.0.day', 'monday');
        $response->assertJsonPath('data.business.business_hours.5.is_closed', true);
        $response->assertJsonFragment(['label' => 'Mon — Fri']);

        $business = BusinessInfo::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($business);
        $this->assertSame(7, $business->businessHours()->count());
        $this->assertTrue($business->businessHours()->where('day', 'saturday')->value('is_closed'));
    }
}
