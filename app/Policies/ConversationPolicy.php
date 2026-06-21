<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ParticipantRole;
use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participantRows()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isUser() || $user->isVendor();
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        if ((int) $user->id === (int) $conversation->created_by) {
            return true;
        }

        return $conversation->participantRows()
            ->where('user_id', $user->id)
            ->where('role', ParticipantRole::Admin)
            ->exists();
    }
}
