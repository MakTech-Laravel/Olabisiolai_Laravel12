<?php

namespace App\Models;

use App\Enums\VerificationWorkflowStatus;
use App\Enums\VerificationWorkflowType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationWorkflow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_info_id',
        'triggered_by',
        'assigned_to',
        'workflow_type',
        'status',
        'title',
        'description',
        'requirements',
        'checklist',
        'due_date',
        'completed_at',
        'completion_notes',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'workflow_type' => VerificationWorkflowType::class,
            'status' => VerificationWorkflowStatus::class,
            'requirements' => 'array',
            'checklist' => 'array',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BusinessInfo, $this>
     */
    public function businessInfo(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
