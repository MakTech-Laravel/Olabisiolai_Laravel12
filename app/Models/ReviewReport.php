<?php

namespace App\Models;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    protected $fillable = [
        'review_id',
        'user_id',
        'reason',
        'description',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReviewReportReason::class,
            'status' => ReviewReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewReportStatus::Pending);
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('status', ReviewReportStatus::Reviewed);
    }
}
