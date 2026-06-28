<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConversationRepositoryInterface
{
    public function findByUuidForUser(string $uuid, User $user): ?Conversation;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, int $perPage, array $filters = []): LengthAwarePaginator;

    public function searchForUser(User $user, string $query): Collection;

    public function unreadMessagesCountForUser(User $user): int;

    public function unreadMessagesCountInConversation(User $user, Conversation $conversation): int;

    public function save(Conversation $conversation): void;

    public function delete(Conversation $conversation): void;

    /**
     * @param  list<int>  $userIds
     */
    public function findDirectBetweenUsers(array $userIds, ?int $businessInfoId = null): ?Conversation;
}
