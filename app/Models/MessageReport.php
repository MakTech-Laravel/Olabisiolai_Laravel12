<?php

namespace App\Models;

use App\Enums\MessageReportReason;
use App\Enums\ReviewReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReport extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'reporter_user_id',
        'reported_user_id',
        'reason',
        'description',
        'status',
        'reviewed_at',
        'reviewed_by_admin_id',
        'admin_note',
        'last_admin_email_subject',
        'last_admin_email_body',
        'last_admin_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => MessageReportReason::class,
            'status' => ReviewReportStatus::class,
            'reviewed_at' => 'datetime',
            'last_admin_email_sent_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ReviewReportStatus::Pending);
    }
}
