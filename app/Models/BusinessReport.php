<?php

namespace App\Models;

use App\Enums\ReviewReportReason;
use App\Enums\ReviewReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessReport extends Model
{
    protected $fillable = [
        'business_info_id',
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
     * @return BelongsTo<BusinessInfo, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class, 'business_info_id');
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
}
