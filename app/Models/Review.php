<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'full_name',
        'is_anonymous',
        'rating',
        'review_text',
        'is_approved',
        'flag_reason',
        'flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_approved' => 'boolean',
            'rating' => 'integer',
            'flagged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BusinessInfo, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class, 'business_id');
    }

    /**
     * @return HasMany<ReviewImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class);
    }

    /**
     * @return HasMany<ReviewReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class);
    }

    /**
     * @return HasMany<ReviewReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    /**
     * Scope to get only approved reviews
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to reviews that are not approved (same rows as the admin “Flagged” tab).
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope to get reviews by rating
     */
    public function scopeByRating(Builder $query, int $rating): Builder
    {
        return $query->where('rating', $rating);
    }

    /**
     * Get display name (handles anonymous reviews)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->is_anonymous ? 'Anonymous' : $this->full_name;
    }
}
