<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ConversationRepository implements ConversationRepositoryInterface
{
    public function findByUuidForUser(string $uuid, User $user): ?Conversation
    {
        return Conversation::query()
            ->forUser($user)
            ->where('uuid', $uuid)
            ->with(self::listRelations())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->forUser($user)
            ->with(self::listRelations())
            ->withCount([
                'messages as unread_count' => function (Builder $q) use ($user): void {
                    $q->where('messages.sender_id', '!=', $user->id)
                        ->whereExists(function ($sub) use ($user): void {
                            $sub->selectRaw('1')
                                ->from('conversation_participants as cp')
                                ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                                ->where('cp.user_id', $user->id)
                                ->where(function ($w): void {
                                    $w->whereNull('cp.last_read_at')
                                        ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                                });
                        });
                },
            ])
            ->recent();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['archived'])) {
            $query->where('is_archived', (bool) $filters['archived']);
        }

        if (! empty($filters['unread'])) {
            $query->whereHas('messages', function (Builder $mq) use ($user): void {
                $mq->where('sender_id', '!=', $user->id)
                    ->whereNull('deleted_at')
                    ->whereExists(function ($sub) use ($user): void {
                        $sub->selectRaw('1')
                            ->from('conversation_participants as cp')
                            ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                            ->where('cp.user_id', $user->id)
                            ->where(function ($w): void {
                                $w->whereNull('cp.last_read_at')
                                    ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                            });
                    });
            });
        }

        if (! empty($filters['verified_only'])) {
            $query->whereHas('participantRows', function (Builder $q) use ($user): void {
                $q->where('user_id', '!=', $user->id)
                    ->whereHas('user.businessInfo', function (Builder $bq): void {
                        $bq->whereNotNull('verified_at');
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function searchForUser(User $user, string $query): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        return Conversation::query()
            ->forUser($user)
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhereHas('messages', function (Builder $mq) use ($like): void {
                        $mq->where('body', 'like', $like);
                    });
            })
            ->with(self::listRelations())
            ->recent()
            ->limit(50)
            ->get();
    }

    /**
     * @return list<string|\Closure>
     */
    private static function listRelations(): array
    {
        return [
            'lastMessage.sender',
            'lastMessage.attachments',
            'participantRows.user:id,uuid,name,first_name,last_name,email,role,image',
            'participantRows.user.messagingPresence',
            'participantRows.user.businessInfo:id,user_id,business_name,logo_path,verified_at',
        ];
    }

    public function unreadMessagesCountInConversation(User $user, Conversation $conversation): int
    {
        return (int) Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->whereExists(function ($sub) use ($user): void {
                $sub->selectRaw('1')
                    ->from('conversation_participants as cp')
                    ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                    ->where('cp.user_id', $user->id)
                    ->where(function ($w): void {
                        $w->whereNull('cp.last_read_at')
                            ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                    });
            })
            ->count();
    }

    public function unreadMessagesCountForUser(User $user): int
    {
        return (int) Message::query()
            ->whereHas('conversation.participantRows', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id);
            })
            ->where('sender_id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->whereExists(function ($sub) use ($user): void {
                $sub->selectRaw('1')
                    ->from('conversation_participants as cp')
                    ->whereColumn('cp.conversation_id', 'messages.conversation_id')
                    ->where('cp.user_id', $user->id)
                    ->where(function ($w): void {
                        $w->whereNull('cp.last_read_at')
                            ->orWhereColumn('messages.created_at', '>', 'cp.last_read_at');
                    });
            })
            ->count();
    }

    public function save(Conversation $conversation): void
    {
        $conversation->save();
    }

    public function delete(Conversation $conversation): void
    {
        $conversation->delete();
    }

    /**
     * @param  list<int>  $userIds
     */
    public function findDirectBetweenUsers(array $userIds): ?Conversation
    {
        sort($userIds);
        $a = $userIds[0] ?? null;
        $b = $userIds[1] ?? null;

        if ($a === null || $b === null) {
            return null;
        }

        return Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participantRows', fn (Builder $q) => $q->where('user_id', $a))
            ->whereHas('participantRows', fn (Builder $q) => $q->where('user_id', $b))
            ->whereDoesntHave('participantRows', fn (Builder $q) => $q->whereNotIn('user_id', [$a, $b]))
            ->whereRaw('(select count(*) from conversation_participants where conversation_participants.conversation_id = conversations.id) = 2')
            ->first();
    }
}
