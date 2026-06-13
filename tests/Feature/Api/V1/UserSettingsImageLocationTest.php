<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class UserSettingsImageLocationTest extends TestCase
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

    public function test_settings_show_includes_location_and_image_url(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'location' => 'Lagos',
            'image' => null,
        ]);

        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/user/settings');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.profile.location', 'Lagos');
        $response->assertJsonPath('data.profile.image_path', null);
        $this->assertStringContainsString(
            'default-header-avatar',
            (string) $response->json('data.profile.image_url'),
        );
    }

    public function test_settings_update_stores_location_and_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'user',
            'location' => null,
            'image' => null,
        ]);
        $token = $user->createToken('test')->accessToken;

        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $response = $this->withToken($token)->patch('/api/v1/user/settings', [
            'location' => 'Abuja',
            'image' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.profile.location', 'Abuja');

        $storedPath = $response->json('data.profile.image_path');
        $this->assertIsString($storedPath);
        $this->assertStringStartsWith('users/'.$user->id.'/profile/', $storedPath);
        Storage::disk('public')->assertExists($storedPath);

        $imageUrl = $response->json('data.profile.image_url');
        $this->assertIsString($imageUrl);
        $this->assertStringContainsString($storedPath, $imageUrl);
    }

    public function test_settings_update_replaces_image_and_deletes_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'user',
            'image' => null,
        ]);
        $token = $user->createToken('test')->accessToken;

        $first = $this->withToken($token)->patch('/api/v1/user/settings', [
            'image' => UploadedFile::fake()->image('one.png', 80, 80),
        ]);
        $first->assertOk();
        $firstPath = $first->json('data.profile.image_path');
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->withToken($token)->patch('/api/v1/user/settings', [
            'image' => UploadedFile::fake()->image('two.png', 80, 80),
        ]);
        $second->assertOk();
        $secondPath = $second->json('data.profile.image_path');
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFalse(Storage::disk('public')->exists($firstPath));
        $this->assertTrue(Storage::disk('public')->exists($secondPath));
    }

    public function test_settings_update_accepts_post_multipart_image_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'user',
            'image' => null,
        ]);
        $token = $user->createToken('test')->accessToken;

        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $response = $this->withToken($token)->post('/api/v1/user/settings', [
            'image' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $storedPath = $response->json('data.profile.image_path');
        $this->assertIsString($storedPath);
        Storage::disk('public')->assertExists($storedPath);
    }
}
