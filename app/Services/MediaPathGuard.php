<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class MediaPathGuard
{
    public function assertOwnedBy(Model $uploadable, string $path, string $attribute = 'path'): void
    {
        $normalized = trim($path);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                $attribute => ['The selected media path is invalid.'],
            ]);
        }

        $exists = Media::query()
            ->where('uploadable_type', $uploadable->getMorphClass())
            ->where('uploadable_id', $uploadable->getKey())
            ->where('path', $normalized)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $attribute => ['The selected media path is invalid or does not belong to this resource.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    public function assertAllOwnedBy(Model $uploadable, array $paths, string $attribute = 'paths'): void
    {
        foreach (array_values($paths) as $index => $path) {
            if (! is_string($path)) {
                throw ValidationException::withMessages([
                    "{$attribute}.{$index}" => ['The selected media path is invalid.'],
                ]);
            }

            $this->assertOwnedBy($uploadable, $path, "{$attribute}.{$index}");
        }
    }
}
