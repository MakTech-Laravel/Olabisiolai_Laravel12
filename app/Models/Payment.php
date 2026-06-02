<?php

namespace App\Models;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PaymentGateway;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'business_info_id',
        'purpose',
        'package_id',
        'amount',
        'currency',
        'tx_ref',
        'gateway',
        'gateway_transaction_id',
        'status',
        'paid_at',
        'is_consumed',
        'metadata',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'is_consumed' => 'boolean',
            'metadata' => 'array',
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'gateway' => PaymentGateway::class,
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
    public function businessInfo(): BelongsTo
    {
        return $this->belongsTo(BusinessInfo::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }

    public function isConsumable(): bool
    {
        return $this->isCompleted() && ! $this->is_consumed;
    }
}
