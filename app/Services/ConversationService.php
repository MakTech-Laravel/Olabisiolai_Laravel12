<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Messaging\ConversationDTO;
use App\Enums\BusinessStatus;
use App\Enums\ConversationType;
use App\Enums\ParticipantRole;
use App\Enums\UserStatus;
use App\Exceptions\ConversationNotFoundException;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConversationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly ConversationInitiationService $initiation,
    ) {}

    public function createConversation(ConversationDTO $dto, User $creator): Conversation
    {
        $max = (int) config('messaging.max_participants_per_conversation', 50);

        if (count($dto->participantUserIds) > $max) {
            throw new \InvalidArgumentException('Too many participants.');
        }

        if ($dto->type === ConversationType::Direct && count($dto->participantUserIds) === 1) {
            $existing = $this->conversations->findDirectBetweenUsers([$creator->id, $dto->participantUserIds[0]]);

            if ($existing !== null) {
                if ($dto->businessInfoId !== null && $existing->business_info_id === null) {
                    $existing->update(['business_info_id' => $dto->businessInfoId]);
                }

                return $existing->load([
                    'lastMessage.sender.businessInfo:id,user_id,business_name,logo_path,verified_at',
                    'lastMessage.attachments',
                    'participantRows.user.messagingPresence',
                    'participantRows.user.businessInfo:id,user_id,business_name,logo_path,verified_at',
                    'participantRows.user.businessInfos:id,user_id,business_name,logo_path,business_status,is_flagged,category_id,location_id,sort_order',
                ]);
            }

            $this->initiation->assertCanInitiateDirect($creator, $dto->participantUserIds);
        }

        return DB::transaction(function () use ($dto, $creator): Conversation {
            $conversation = new Conversation([
                'uuid' => (string) Str::uuid(),
                'type' => $dto->type,
                'name' => $dto->name,
                'created_by' => $creator->id,
                'business_info_id' => $dto->businessInfoId,
                'tenant_id' => $dto->tenantId,
            ]);
            $this->conversations->save($conversation);

            $participantIds = array_unique(array_merge([$creator->id], $dto->participantUserIds));

            foreach ($participantIds as $userId) {
                $conversation->participantRows()->create([
                    'user_id' => $userId,
                    'role' => $userId === $creator->id ? ParticipantRole::Admin : ParticipantRole::Member,
                    'joined_at' => now(),
                ]);
            }

            $conversation->load([
                'lastMessage.sender.businessInfo:id,user_id,business_name,logo_path,verified_at',
                'lastMessage.attachments',
                'participantRows.user.messagingPresence',
                'participantRows.user.businessInfo:id,user_id,business_name,logo_path,verified_at',
                'participantRows.user.businessInfos:id,user_id,business_name,logo_path,business_status,is_flagged,category_id,location_id,sort_order',
            ]);

            Cache::forget($this->participantCacheKey($conversation->uuid));

            return $conversation;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getConversationsForUser(User $user, array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return $this->conversations->paginateForUser($user, $perPage, $filters);
    }

    public function getConversation(string $uuid, User $user): Conversation
    {
        $conversation = $this->conversations->findByUuidForUser($uuid, $user);

        if ($conversation === null) {
            throw ConversationNotFoundException::forUuid($uuid);
        }

        $this->cachedParticipants($conversation);

        return $conversation;
    }

    public function searchConversations(User $user, string $query): Collection
    {
        return $this->conversations->searchForUser($user, $query);
    }

    /**
     * Find active users the viewer can start a direct message with.
     */
    public function searchRecipients(User $viewer, string $query): Collection
    {
        if ($viewer->isVendor()) {
            return new Collection();
        }

        $trimmed = trim($query);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $trimmed) . '%';
        $emailLike = '%' . str_replace(['%', '_'], ['\\%', '\\_'], strtolower($trimmed)) . '%';

        return User::query()
            ->where('id', '!=', $viewer->id)
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('uuid')
            ->where('role', 'vendor')
            ->whereHas('businessInfo', function (Builder $bq): void {
                $bq->where('business_status', BusinessStatus::Active->value)
                    ->where('is_flagged', false);
            })
            ->with([
                'businessInfo:id,user_id,business_name,logo_path,verified_at,category_id',
                'businessInfo.category:id,name',
            ])
            ->where(function (Builder $q) use ($like, $emailLike): void {
                $q->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw('LOWER(email) LIKE ?', [$emailLike])
                    ->orWhereHas('businessInfo', function (Builder $bq) use ($like): void {
                        $bq->where('business_name', 'like', $like);
                    });
            })
            ->orderBy('name')
            ->limit(15)
            ->get();
    }

    public function getUnreadCount(User $user): int
    {
        return $this->conversations->unreadMessagesCountForUser($user);
    }

    public function deleteConversation(Conversation $conversation): void
    {
        Cache::forget($this->participantCacheKey($conversation->uuid));
        $this->conversations->delete($conversation);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function cachedParticipants(Conversation $conversation): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, int> */
        return Cache::remember(
            $this->participantCacheKey($conversation->uuid),
            300,
            fn(): \Illuminate\Support\Collection => $conversation->participantRows()
                ->pluck('user_id')
                ->unique()
                ->values()
        );
    }

    private function participantCacheKey(string $uuid): string
    {
        return sprintf('conversation.%s.participants', $uuid);
    }
}
