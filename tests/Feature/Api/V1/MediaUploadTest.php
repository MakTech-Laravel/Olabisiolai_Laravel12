<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\OptimizeImage;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class MediaUploadTest extends TestCase
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

    public function test_user_can_upload_media_and_job_is_dispatched(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $response = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => $file,
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotEmpty($response->json('data.id'));
        $this->assertNotEmpty($response->json('data.url'));

        $this->assertDatabaseHas('media', [
            'id' => $response->json('data.id'),
            'uploadable_type_key' => 'profile',
            'uploadable_id' => $user->id,
            'status' => 'pending',
        ]);

        Storage::disk('public')->assertExists($response->json('data.path'));

        Queue::assertPushed(OptimizeImage::class, function (OptimizeImage $job) use ($response): bool {
            return $job->mediaId === (int) $response->json('data.id');
        });
    }

    public function test_invalid_mime_is_rejected(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['media.max_upload_size_mb' => 1]);

        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $token = $user->createToken('test')->accessToken;

        $file = UploadedFile::fake()->image('big.jpg')->size(2048);

        $response = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => $file,
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $response->assertUnprocessable();
        Queue::assertNothingPushed();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_missing_uploadable_fields_are_rejected(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $token = $user->createToken('test')->accessToken;

        $response = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => UploadedFile::fake()->image('photo.jpg', 80, 80),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['uploadable_type', 'uploadable_id']);
        Queue::assertNothingPushed();
    }

    public function test_unauthorized_role_is_forbidden(): void
    {
        Storage::fake('public');
        Queue::fake();

        // users.role ENUM only allows user|vendor; mutate the in-memory model so
        // EnsureRole rejects without persisting an invalid enum value.
        $user = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $user->role = 'admin';
        Passport::actingAs($user, guard: 'api');

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => UploadedFile::fake()->image('photo.jpg', 80, 80),
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $response->assertForbidden();
        Queue::assertNothingPushed();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_duplicate_file_returns_existing_media_without_second_job(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test')->accessToken;

        $seed = UploadedFile::fake()->image('same.jpg', 120, 120);
        $binary = (string) file_get_contents($seed->getRealPath());

        $first = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => UploadedFile::fake()->createWithContent('same.jpg', $binary),
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $first->assertCreated();
        $firstId = (int) $first->json('data.id');

        $second = $this->withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/media', [
                'file' => UploadedFile::fake()->createWithContent('same-again.jpg', $binary),
                'uploadable_type' => 'profile',
                'uploadable_id' => $user->id,
            ]);

        $second->assertOk();
        $second->assertJsonPath('data.id', $firstId);
        $this->assertSame(1, Media::query()->count());

        Queue::assertPushed(OptimizeImage::class, 1);
    }
}
