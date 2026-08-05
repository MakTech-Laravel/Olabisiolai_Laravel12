<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Throwable;

/**
 * Lossy re-encode via Intervention Image (works without jpegoptim/optipng binaries),
 * then optionally run Spatie binary optimizers when they are available.
 */
final class MediaOptimizeService
{
    public function __construct(
        private readonly OptimizerChain $optimizerChain,
    ) {}

    /**
     * Optimize the image at an absolute filesystem path in place.
     *
     * @return int Size in bytes after optimization
     */
    public function optimizePath(string $absolutePath, ?string $mimeType = null): int
    {
        if (! is_file($absolutePath)) {
            throw new \RuntimeException("Image file not found: {$absolutePath}");
        }

        $this->reencodeWithIntervention($absolutePath, $mimeType);
        $this->trySpatieBinaryOptimize($absolutePath);

        clearstatcache(true, $absolutePath);

        $size = filesize($absolutePath);

        return $size === false ? 0 : (int) $size;
    }

    private function reencodeWithIntervention(string $absolutePath, ?string $mimeType): void
    {
        $maxEdge = (int) config('media.optimize.max_edge', 2560);
        $jpegQuality = (int) config('media.optimize.jpeg_quality', 82);
        $webpQuality = (int) config('media.optimize.webp_quality', 82);

        $image = Image::decodePath($absolutePath);
        $encoded = null;

        try {
            if ($maxEdge > 0 && (max($image->width(), $image->height()) > $maxEdge)) {
                $image->scaleDown(width: $maxEdge, height: $maxEdge);
            }

            $mime = strtolower((string) ($mimeType ?: mime_content_type($absolutePath) ?: ''));

            $encoded = match (true) {
                str_contains($mime, 'png') => $image->encode(new PngEncoder()),
                str_contains($mime, 'webp') => $image->encode(new WebpEncoder(quality: $webpQuality)),
                str_contains($mime, 'gif') => null,
                default => $image->encode(new JpegEncoder(quality: $jpegQuality)),
            };

            // GIFs: leave binary optimization / skip Intervention re-encode (animation).
            if ($encoded === null) {
                return;
            }

            if (file_put_contents($absolutePath, (string) $encoded) === false) {
                throw new \RuntimeException("Failed to write optimized image: {$absolutePath}");
            }
        } finally {
            unset($image, $encoded);
        }
    }

    private function trySpatieBinaryOptimize(string $absolutePath): void
    {
        try {
            // Missing binaries fail per-optimizer; Spatie swallows them unless throws() is set.
            $this->optimizerChain
                ->throws(function (Throwable $exception): void {
                    Log::warning('Spatie image binary optimizer skipped/failed.', [
                        'message' => $exception->getMessage(),
                    ]);
                })
                ->optimize($absolutePath);
        } catch (Throwable $e) {
            Log::warning('Spatie image optimizer chain failed.', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
