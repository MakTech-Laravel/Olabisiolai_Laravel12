<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read string $uuid
 */
final class Conversation extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @use HasFactory<ConversationFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'conversations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'name',
        'last_message_id',
        'created_by',
        'business_info_id',
        'is_archived',
        'tenant_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Conversation $conversation): void {
            if ($conversation->uuid === null || $conversation->uuid === '') {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'is_archived' => 'boolean',
            'tenant_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return BelongsToMany<User, $this, ConversationParticipant>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot(['role', 'joined_at', 'last_read_at', 'is_muted'])
            ->withTimestamps(false);
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participantRows(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participantRows', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc($query->qualifyColumn('updated_at'));
    }
}
