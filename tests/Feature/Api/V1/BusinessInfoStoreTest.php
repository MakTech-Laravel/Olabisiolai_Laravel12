<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class BusinessInfoStoreTest extends TestCase
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

    public function test_verified_vendor_can_fetch_form_options(): void
    {
        Category::factory()->count(2)->create();

        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/vendor/business/form-options');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'categories',
                'locations',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.locations'));
    }

    public function test_verified_vendor_can_create_business_profile_with_uploads(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/business', [
            'category_id' => $category->id,
            'business_name' => 'Acme Services',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'business_description' => 'Professional home and office services.',
            'services' => ['Repairs', 'Installations'],
            'phone' => '+2348012345678',
            'whatsapp' => '+2348098765432',
            'website' => 'https://acme.example',
            'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            'cover_photos' => [
                UploadedFile::fake()->image('cover1.png', 200, 200),
                UploadedFile::fake()->image('cover2.png', 200, 200),
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.business.business_name', 'Acme Services');
        $response->assertJsonPath('data.business.category.id', $category->id);

        $this->assertDatabaseHas('business_info', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'business_name' => 'Acme Services',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
        ]);

        $business = BusinessInfo::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('public')->assertExists((string) $business->logo_path);
        foreach ($business->cover_photo_paths ?? [] as $path) {
            Storage::disk('public')->assertExists((string) $path);
        }
    }

    public function test_invalid_location_combination_returns_validation_error(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->postJson('/api/v1/vendor/business', [
            'category_id' => $category->id,
            'business_name' => 'Acme Services',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Nonexistent City',
            'business_description' => 'Description here.',
            'services' => ['One'],
            'phone' => '+2348012345678',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover_photos' => [UploadedFile::fake()->image('c1.png')],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['city']);
    }

    public function test_second_create_returns_error(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $payload = [
            'category_id' => $category->id,
            'business_name' => 'First Co',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'business_description' => 'First profile.',
            'services' => ['A'],
            'phone' => '+2348011111111',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'cover_photos' => [UploadedFile::fake()->image('c1.png')],
        ];

        $this->withToken($token)->postJson('/api/v1/vendor/business', $payload)->assertCreated();

        $payload['business_name'] = 'Second Co';
        $this->withToken($token)->postJson('/api/v1/vendor/business', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_verified_vendor_can_edit_business_profile_without_changing_url(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $existingLogoPath = UploadedFile::fake()->image('old-logo.png')->store('businesses/'.$user->id.'/logo', 'public');
        $existingCoverPath = UploadedFile::fake()->image('old-cover.png')->store('businesses/'.$user->id.'/covers', 'public');

        BusinessInfo::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'business_name' => 'Old Name',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'business_description' => 'Old description',
            'services_offered' => ['Old Service'],
            'phone' => '+2348000000000',
            'logo_path' => $existingLogoPath,
            'cover_photo_paths' => [$existingCoverPath],
        ]);

        $response = $this->withToken($token)->putJson('/api/v1/vendor/business/create', [
            'category_id' => $newCategory->id,
            'business_name' => 'Updated Business Name',
            'location' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'business_description' => 'Updated description.',
            'services' => ['Updated Service'],
            'phone' => '+2348111111111',
            'whatsapp' => '+2348222222222',
            'website' => 'https://updated.example',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.business.business_name', 'Updated Business Name');
        $response->assertJsonPath('data.business.category.id', $newCategory->id);

        $this->assertDatabaseHas('business_info', [
            'user_id' => $user->id,
            'category_id' => $newCategory->id,
            'business_name' => 'Updated Business Name',
            'phone' => '+2348111111111',
        ]);

        Storage::disk('public')->assertExists($existingLogoPath);
        Storage::disk('public')->assertExists($existingCoverPath);
    }

    public function test_unauthenticated_store_returns_401(): void
    {
        $this->postJson('/api/v1/vendor/business', [])->assertUnauthorized();
    }

    public function test_show_returns_404_when_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/vendor/business')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_role_user_cannot_access_vendor_business_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $this->withToken($token)
            ->getJson('/api/v1/vendor/business/form-options')
            ->assertForbidden();
    }
}
