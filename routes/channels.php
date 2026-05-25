<?php

declare(strict_types=1);

use App\Broadcasting\ChannelNames;
use App\Models\Admin;
use App\Models\User;
use App\Services\AdminMessagingUserResolver;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User|Admin|null $actor, int $conversationId) {
    $user = $actor instanceof Admin
        ? AdminMessagingUserResolver::resolve($actor)
        : $actor;

    if (! $user instanceof User) {
        return false;
    }

    if (! $user->conversations()->where('conversations.id', $conversationId)->exists()) {
        return false;
    }

    $channelName = (string) request()->input('channel_name', '');

    if (str_starts_with($channelName, 'presence-')) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => null,
        ];
    }

    return true;
});

Broadcast::channel('user.{userId}', function (User|Admin|null $actor, int $userId) {
    if ($actor instanceof Admin) {
        $actor = AdminMessagingUserResolver::resolve($actor);
    }

    if (! $actor instanceof User) {
        return false;
    }

    return (int) $actor->id === (int) $userId;
});

Broadcast::channel('admin.{adminId}', function (User|Admin|null $user, int $adminId) {
    if (! $user instanceof Admin) {
        return false;
    }

    return (int) $user->id === (int) $adminId;
});

Broadcast::channel(ChannelNames::ADMIN_NOTIFICATIONS, function (User|Admin|null $user) {
    return $user instanceof Admin;
});
