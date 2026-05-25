<?php

namespace App\Models;

use App\Enums\VerificationNoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_info_id',
        'added_by',
        'note_type',
        'note',
        'metadata',
        'is_visible_to_vendor',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'note_type' => VerificationNoteType::class,
            'metadata' => 'array',
            'is_visible_to_vendor' => 'boolean',
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
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
