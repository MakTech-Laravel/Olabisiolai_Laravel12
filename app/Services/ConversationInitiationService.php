<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Validation\ValidationException;

final class ConversationInitiationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly UserFollowService $userFollowService,
    ) {}

    /**
     * @param  list<int>  $participantUserIds
     */
    public function assertCanInitiateDirect(User $creator, array $participantUserIds): void
    {
        if ($creator->isAdmin()) {
            return;
        }

        if (count($participantUserIds) !== 1) {
            throw ValidationException::withMessages([
                'participants' => ['Direct conversations require exactly one other participant.'],
            ]);
        }

        $targetId = (int) $participantUserIds[0];
        $target = User::query()->find($targetId);

        if (! $target instanceof User) {
            throw ValidationException::withMessages([
                'participants' => ['Recipient not found.'],
            ]);
        }

        if ((int) $creator->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'participants' => ['You cannot message yourself.'],
            ]);
        }

        if ($this->userFollowService->isFollowableVendor($target)) {
            return;
        }

        throw ValidationException::withMessages([
            'participants' => ['You can only message active business listings.'],
        ]);
    }

    public function assertNonCreatorCannotSendFirstMessage(Conversation $conversation, User $sender): void
    {
        if ($conversation->type !== ConversationType::Direct) {
            return;
        }

        $hasIncomingFromPeer = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $sender->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasIncomingFromPeer) {
            return;
        }

        if ((int) $conversation->created_by !== (int) $sender->id) {
            throw ValidationException::withMessages([
                'body' => ['You can only reply after the other person sends the first message.'],
            ]);
        }
    }
}
