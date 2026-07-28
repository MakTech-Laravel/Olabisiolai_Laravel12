<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\UploadableType;
use App\Jobs\OptimizeImage;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

final class MediaUploadService
{
    /**
     * @return array{media: Media, created: bool}
     */
    public function store(UploadedFile $file, Model $uploadable, UploadableType $type): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to read the uploaded file.'],
            ]);
        }

        try {
            Image::decodePath($realPath);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => ['The file is not a readable image.'],
            ]);
        }

        $hash = hash_file('sha1', $realPath);
        if ($hash === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to hash the uploaded file.'],
            ]);
        }

        $existing = Media::query()
            ->where('uploadable_type', $uploadable->getMorphClass())
            ->where('uploadable_id', $uploadable->getKey())
            ->where('file_hash', $hash)
            ->first();

        if ($existing !== null) {
            return ['media' => $existing, 'created' => false];
        }

        $disk = (string) config('media.disk', 'public');
        $extension = $this->extensionFor($file);
        $path = sprintf(
            '%s/%s/%s/%s.%s',
            trim((string) config('media.path_prefix', 'uploads'), '/'),
            now()->format('Y'),
            now()->format('m'),
            (string) Str::uuid(),
            $extension,
        );

        $storedPath = null;

        try {
            $media = DB::transaction(function () use ($file, $uploadable, $type, $disk, $path, $hash, &$storedPath): Media {
                $storedPath = $file->storeAs(
                    dirname($path),
                    basename($path),
                    $disk,
                );

                if ($storedPath === false) {
                    throw ValidationException::withMessages([
                        'file' => ['Failed to store the uploaded file.'],
                    ]);
                }

                $media = Media::query()->create([
                    'uploadable_type' => $uploadable->getMorphClass(),
                    'uploadable_id' => $uploadable->getKey(),
                    'uploadable_type_key' => $type->value,
                    'path' => $storedPath,
                    'disk' => $disk,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => (string) $file->getMimeType(),
                    'file_hash' => $hash,
                    'size_before' => $file->getSize() ?: 0,
                    'size_after' => null,
                    'status' => MediaStatus::Pending,
                ]);

                OptimizeImage::dispatch($media->id)->afterCommit();

                return $media;
            });
        } catch (Throwable $e) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk($disk)->delete($storedPath);
            }

            $duplicate = Media::query()
                ->where('uploadable_type', $uploadable->getMorphClass())
                ->where('uploadable_id', $uploadable->getKey())
                ->where('file_hash', $hash)
                ->first();

            if ($duplicate !== null) {
                return ['media' => $duplicate, 'created' => false];
            }

            throw $e;
        }

        return ['media' => $media, 'created' => true];
    }

    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => strtolower($file->getClientOriginalExtension() ?: 'bin'),
        };
    }
}
