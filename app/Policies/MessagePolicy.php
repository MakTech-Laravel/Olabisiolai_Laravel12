<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

final class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->participantRows()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }
}
