<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\MediaStatus;
use App\Jobs\OptimizeImage;
use App\Models\Media;
use App\Models\User;
use App\Services\MediaOptimizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OptimizeImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_optimizes_jpeg_and_stores_smaller_size_after(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $relativePath = 'uploads/2026/07/test-image.jpg';
        $absolutePath = Storage::disk('public')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $this->writeLargeJpeg($absolutePath, quality: 100);
        $before = (int) filesize($absolutePath);

        $media = Media::query()->create([
            'uploadable_type' => $user->getMorphClass(),
            'uploadable_id' => $user->id,
            'uploadable_type_key' => 'profile',
            'path' => $relativePath,
            'disk' => 'public',
            'original_filename' => 'test-image.jpg',
            'mime_type' => 'image/jpeg',
            'file_hash' => sha1_file($absolutePath) ?: 'hash',
            'size_before' => $before,
            'size_after' => null,
            'status' => MediaStatus::Pending,
        ]);

        (new OptimizeImage($media->id))->handle(app(MediaOptimizeService::class));

        $media->refresh();
        $this->assertSame(MediaStatus::Optimized, $media->status);
        $this->assertNotNull($media->size_after);
        $this->assertLessThan($before, (int) $media->size_after);
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

        $beforeMtime = Storage::disk('public')->lastModified($relativePath);

        (new OptimizeImage($media->id))->handle(app(MediaOptimizeService::class));

        $media->refresh();
        $this->assertSame(MediaStatus::Optimized, $media->status);
        $this->assertSame(18, $media->size_after);
        $this->assertSame($beforeMtime, Storage::disk('public')->lastModified($relativePath));
    }

    public function test_optimizer_reencodes_jpeg_and_reduces_size(): void
    {
        Storage::fake('public');

        $relativePath = 'uploads/2026/07/large.jpg';
        $absolutePath = Storage::disk('public')->path($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);

        $this->writeLargeJpeg($absolutePath, quality: 100);
        $before = (int) filesize($absolutePath);
        $this->assertGreaterThan(10_000, $before);

        $after = app(MediaOptimizeService::class)->optimizePath($absolutePath, 'image/jpeg');

        $this->assertLessThan($before, $after);
        $this->assertSame($after, (int) filesize($absolutePath));
    }

    private function writeLargeJpeg(string $absolutePath, int $quality): void
    {
        $image = imagecreatetruecolor(1600, 1200);
        $this->assertNotFalse($image);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefilledrectangle($image, 0, 0, 1599, 1199, $red);
        imagejpeg($image, $absolutePath, $quality);
        imagedestroy($image);
    }
}
