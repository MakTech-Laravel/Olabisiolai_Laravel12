<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Services\MediaOptimizeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class OptimizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10];

    public function __construct(
        public int $mediaId,
    ) {}

    public function handle(MediaOptimizeService $optimizer): void
    {
        $media = Media::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        if ($media->status === MediaStatus::Optimized) {
            return;
        }

        $media->status = MediaStatus::Processing;
        $media->save();

        $absolutePath = Storage::disk($media->disk)->path($media->path);
        $previousLimit = ini_get('memory_limit');
        $boost = (string) config('media.optimize.memory_limit', '512M');
        if ($boost !== '') {
            ini_set('memory_limit', $boost);
        }

        try {
            $sizeAfter = $optimizer->optimizePath($absolutePath, $media->mime_type);

            $media->size_after = $sizeAfter > 0 ? $sizeAfter : $media->size_before;
            $media->status = MediaStatus::Optimized;
            $media->save();
        } catch (Throwable $e) {
            Log::error('Image optimization failed.', [
                'media_id' => $media->id,
                'path' => $media->path,
                'exception' => $e->getMessage(),
            ]);

            $media->status = MediaStatus::Failed;
            $media->save();

            throw $e;
        } finally {
            if (is_string($previousLimit) && $previousLimit !== '') {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }
}
