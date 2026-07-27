<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploadable_type',
        'uploadable_id',
        'uploadable_type_key',
        'path',
        'disk',
        'original_filename',
        'mime_type',
        'file_hash',
        'size_before',
        'size_after',
        'status',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'uploadable_id' => 'integer',
            'size_before' => 'integer',
            'size_after' => 'integer',
            'status' => MediaStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function sizeKb(): float
    {
        $bytes = $this->size_after ?? $this->size_before;

        return round($bytes / 1024, 2);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Processing);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOptimized(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Optimized);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Failed);
    }
}
