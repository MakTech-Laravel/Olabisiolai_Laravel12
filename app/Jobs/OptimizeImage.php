<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChain;
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

    public function handle(OptimizerChain $optimizer): void
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

        try {
            $optimizer->optimize($absolutePath);

            clearstatcache(true, $absolutePath);
            $sizeAfter = is_file($absolutePath) ? (int) filesize($absolutePath) : $media->size_before;

            $media->size_after = $sizeAfter;
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
        }
    }
}
