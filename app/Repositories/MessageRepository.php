<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class MessageRepository implements MessageRepositoryInterface
{
    public function findByUuidForUser(string $uuid, User $user): ?Message
    {
        return Message::query()
            ->forUser($user)
            ->where('uuid', $uuid)
            ->with([
                'sender.businessInfo:id,user_id,logo_path,verified_at',
                'attachments',
                'reads',
                'parent.sender',
                'conversation.participantRows.user:id,name',
            ])
            ->first();
    }

    public function cursorForConversation(Conversation $conversation, int $perPage, ?string $cursor): CursorPaginator
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->with([
                'sender.businessInfo:id,user_id,logo_path,verified_at',
                'attachments',
                'reads',
                'parent.sender',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    public function save(Message $message): void
    {
        $message->save();
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }
}
