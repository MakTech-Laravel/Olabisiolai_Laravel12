<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentMethod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'cardholder_name',
        'email',
        'phone',
        'last_four',
        'card_brand',
        'exp_month',
        'exp_year',
        'billing_line1',
        'billing_city',
        'billing_state',
        'billing_country',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
