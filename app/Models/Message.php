<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property-read int $id
 * @property-read string $uuid
 */
final class Message extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->with(['conversation.participantRows'])
            ->firstOrFail();
    }

    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_id',
        'parent_id',
        'body',
        'type',
        'status',
        'edited_at',
        'tenant_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Message $message): void {
            if ($message->uuid === null || $message->uuid === '') {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'edited_at' => 'datetime',
            'tenant_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MessageRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('conversation.participantRows', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeUnread(Builder $query, User $user): Builder
    {
        return $query->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            });
    }
}
