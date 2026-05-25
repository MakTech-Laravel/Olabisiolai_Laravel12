<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface MessageRepositoryInterface
{
    public function findByUuidForUser(string $uuid, User $user): ?Message;

    public function cursorForConversation(Conversation $conversation, int $perPage, ?string $cursor): CursorPaginator;

    public function save(Message $message): void;

    public function delete(Message $message): void;
}
