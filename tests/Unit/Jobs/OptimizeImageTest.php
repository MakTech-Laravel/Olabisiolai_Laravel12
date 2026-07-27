<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\MediaStatus;
use App\Jobs\OptimizeImage;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\ImageOptimizer\OptimizerChain;
use Tests\TestCase;

final class OptimizeImageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_calls_optimizer_chain_once_with_absolute_path(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $relativePath = 'uploads/2026/07/test-image.jpg';
        Storage::disk('public')->put($relativePath, 'fake-image-bytes');

        $media = Media::query()->create([
            'uploadable_type' => $user->getMorphClass(),
            'uploadable_id' => $user->id,
            'uploadable_type_key' => 'profile',
            'path' => $relativePath,
            'disk' => 'public',
            'original_filename' => 'test-image.jpg',
            'mime_type' => 'image/jpeg',
            'file_hash' => sha1('fake-image-bytes'),
            'size_before' => 16,
            'size_after' => null,
            'status' => MediaStatus::Pending,
        ]);

        $absolutePath = Storage::disk('public')->path($relativePath);

        $optimizer = Mockery::mock(OptimizerChain::class);
        $optimizer->shouldReceive('optimize')
            ->once()
            ->with($absolutePath)
            ->andReturnNull();

        $this->app->instance(OptimizerChain::class, $optimizer);

        (new OptimizeImage($media->id))->handle($optimizer);

        $media->refresh();
        $this->assertSame(MediaStatus::Optimized, $media->status);
        $this->assertNotNull($media->size_after);
    }

    public function test_job_is_noop_when_already_optimized(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $relativePath = 'uploads/2026/07/already-optimized.jpg';
        Storage::disk('public')->put($relativePath, 'already-optimized');

        $media = Media::query()->create([
            'uploadable_type' => $user->getMorphClass(),
            'uploadable_id' => $user->id,
            'uploadable_type_key' => 'profile',
            'path' => $relativePath,
            'disk' => 'public',
            'original_filename' => 'already-optimized.jpg',
            'mime_type' => 'image/jpeg',
            'file_hash' => sha1('already-optimized'),
            'size_before' => 20,
            'size_after' => 18,
            'status' => MediaStatus::Optimized,
        ]);

        $optimizer = Mockery::mock(OptimizerChain::class);
        $optimizer->shouldReceive('optimize')->never();

        $this->app->instance(OptimizerChain::class, $optimizer);

        (new OptimizeImage($media->id))->handle($optimizer);

        $media->refresh();
        $this->assertSame(MediaStatus::Optimized, $media->status);
        $this->assertSame(18, $media->size_after);
    }
}
