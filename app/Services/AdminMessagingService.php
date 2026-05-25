<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Messaging\CreateConversationAction;
use App\Actions\Messaging\SendMessageAction;
use App\DTOs\Messaging\ConversationDTO;
use App\DTOs\Messaging\MessageDTO;
use App\Enums\ConversationType;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AdminMessagingService
{
    public function __construct(
        private readonly CreateConversationAction $createConversation,
        private readonly SendMessageAction $sendMessage,
    ) {}

    public static function defaultPlatformAdmin(): Admin
    {
        $email = (string) config('messaging.platform_admin_email', 'superadmin@dev.com');

        return Admin::query()->where('email', $email)->firstOrFail();
    }

    public function messagingUser(Admin $admin): User
    {
        return AdminMessagingUserResolver::resolve($admin);
    }

    public function startOrGetVendorConversation(Admin $admin, User $vendor): Conversation
    {
        $sender = $this->messagingUser($admin);

        $dto = new ConversationDTO(
            type: ConversationType::Direct,
            name: null,
            participantUserIds: [(int) $vendor->id],
        );

        return $this->createConversation->execute($dto, $sender);
    }

    public function startOrGetVendorConversationForBusiness(Admin $admin, BusinessInfo $businessInfo): Conversation
    {
        $businessInfo->loadMissing('user');

        $vendor = $businessInfo->user;

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'business_info_id' => ['This business has no vendor account.'],
            ]);
        }

        return $this->startOrGetVendorConversation($admin, $vendor);
    }

    /**
     * Vendor ↔ platform admin thread (most recent admin-linked conversation, or new with default admin).
     */
    public function resolveVendorAdminConversation(User $vendor): Conversation
    {
        $adminUserIds = AdminMessagingUserResolver::messagingUserIds();

        if ($adminUserIds !== []) {
            $existing = Conversation::query()
                ->where('type', ConversationType::Direct)
                ->whereHas('participantRows', fn ($q) => $q->where('user_id', $vendor->id))
                ->whereHas('participantRows', fn ($q) => $q->whereIn('user_id', $adminUserIds))
                ->orderByDesc('updated_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->startOrGetVendorConversation(self::defaultPlatformAdmin(), $vendor);
    }

    public function sendToVendor(Admin $admin, Conversation $conversation, string $body): Message
    {
        $sender = $this->messagingUser($admin);

        $dto = new MessageDTO(
            conversationUuid: (string) $conversation->uuid,
            body: $body,
            parentId: null,
            attachmentIds: [],
        );

        return $this->sendMessage->execute($dto, $sender);
    }
}
