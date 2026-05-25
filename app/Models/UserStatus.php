<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PresenceUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messaging presence row for {@see User} (`user_statuses` table).
 *
 * @property-read int $id
 * @property-read int $user_id
 */
final class UserStatus extends Model
{
    public $timestamps = false;

    protected $table = 'user_statuses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PresenceUserStatus::class,
            'last_seen_at' => 'datetime',
            'updated_at' => 'datetime',
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
