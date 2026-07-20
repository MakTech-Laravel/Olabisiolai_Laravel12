<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Messaging\MessageDTO;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageRead as MessageReadBroadcast;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Exceptions\ConversationNotFoundException;
use App\Jobs\ProcessAttachment;
use App\Jobs\SendAwayMessageAlert;
use App\Jobs\SendMessageNotification;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Support\MessagingHelper;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MessageService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly BroadcastService $broadcast,
        private readonly PresenceService $presence,
    ) {}

    public function sendMessage(MessageDTO $dto, User $sender): Message
    {
        $conversation = $this->conversations->findByUuidForUser($dto->conversationUuid, $sender);

        if ($conversation === null) {
            throw ConversationNotFoundException::forUuid($dto->conversationUuid);
        }

        Gate::forUser($sender)->authorize('view', $conversation);

        if ($dto->parentId !== null) {
            $parentOk = Message::query()
                ->whereKey($dto->parentId)
                ->where('conversation_id', $conversation->id)
                ->exists();

            if (! $parentOk) {
                throw ValidationException::withMessages([
                    'parent_uuid' => ['Invalid parent message for this conversation.'],
                ]);
            }
        }

        if ($dto->attachmentIds !== []) {
            $validCount = Attachment::query()
                ->whereIn('id', $dto->attachmentIds)
                ->where('uploader_id', $sender->id)
                ->whereNull('message_id')
                ->count();

            if ($validCount !== count($dto->attachmentIds)) {
                throw ValidationException::withMessages([
                    'attachment_ids' => ['One or more attachments are invalid or already linked.'],
                ]);
            }
        }

        $body = MessagingHelper::sanitizeBody($dto->body);

        if (($body === null || $body === '') && $dto->attachmentIds === []) {
            throw ValidationException::withMessages([
                'body' => ['A message body or at least one attachment is required.'],
            ]);
        }

        $result = DB::transaction(function () use ($dto, $sender, $conversation, $body): array {
            $message = new Message([
                'uuid' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'parent_id' => $dto->parentId,
                'body' => $body,
                'type' => $dto->attachmentIds !== [] && ($body === null || $body === '')
                    ? MessageType::Attachment
                    : MessageType::Text,
                'status' => MessageStatus::Sent,
                'tenant_id' => $dto->tenantId,
            ]);

            $this->messages->save($message);

            $conversation->loadMissing('participantRows');
            $this->markDeliveredWhenRecipientsOnline($message, $sender, $conversation);

            if ($dto->attachmentIds !== []) {
                Attachment::query()
                    ->whereIn('id', $dto->attachmentIds)
                    ->where('uploader_id', $sender->id)
                    ->whereNull('message_id')
                    ->update(['message_id' => $message->id]);
            }

            $conversation->forceFill(['last_message_id' => $message->id])->save();

            Cache::forget(sprintf('conversation.%s.participants', $conversation->uuid));

            $markedAsRead = $this->persistUnreadPeerMessagesAsRead($conversation, $sender, null);

            $message->load([
                'sender.businessInfo:id,user_id,logo_path,verified_at',
                'attachments',
                'reads',
                'parent.sender',
            ]);

            return ['message' => $message, 'marked_as_read' => $markedAsRead];
        });

        foreach ($result['marked_as_read'] as $readMessage) {
            $this->broadcast->broadcast(new MessageReadBroadcast($readMessage, $sender));
        }

        $message = $result['message'];

        $this->broadcast->broadcast(new MessageSent($message));

        foreach ($message->attachments as $attachment) {
            ProcessAttachment::dispatch($attachment->id);
        }

        SendMessageNotification::dispatch($message->id)->afterCommit();
        // SendAwayMessageAlert::dispatch($message->id)->afterCommit();

        return $message;
    }

    public function editMessage(string $uuid, string $body, User $user): Message
    {
        $message = $this->messages->findByUuidForUser($uuid, $user);

        if ($message === null) {
            throw (new ModelNotFoundException)->setModel(Message::class, [$uuid]);
        }

        Gate::forUser($user)->authorize('update', $message);

        $sanitized = MessagingHelper::sanitizeBody($body);

        if ($sanitized === null || $sanitized === '') {
            throw ValidationException::withMessages([
                'body' => ['The body may not be empty.'],
            ]);
        }

        $message->forceFill([
            'body' => $sanitized,
            'edited_at' => now(),
        ]);

        $this->messages->save($message);

        $message->load([
            'sender.businessInfo:id,user_id,logo_path,verified_at',
            'attachments',
            'reads',
            'parent.sender',
        ]);

        $this->broadcast->broadcast(new MessageEdited($message));

        return $message;
    }

    public function deleteMessage(string $uuid, User $user): bool
    {
        $message = $this->messages->findByUuidForUser($uuid, $user);

        if ($message === null) {
            return false;
        }

        Gate::forUser($user)->authorize('delete', $message);

        $this->messages->delete($message);

        $this->broadcast->broadcast(new MessageDeleted($message));

        return true;
    }

    public function getMessages(string $conversationUuid, User $user, ?string $cursor = null): CursorPaginator
    {
        $conversation = $this->conversations->findByUuidForUser($conversationUuid, $user);

        if ($conversation === null) {
            throw ConversationNotFoundException::forUuid($conversationUuid);
        }

        Gate::forUser($user)->authorize('view', $conversation);

        return $this->messages->cursorForConversation($conversation, 30, $cursor);
    }

    public function markAsRead(string $messageUuid, User $user): void
    {
        $message = $this->messages->findByUuidForUser($messageUuid, $user);

        if ($message === null) {
            throw (new ModelNotFoundException)->setModel(Message::class, [$messageUuid]);
        }

        Gate::forUser($user)->authorize('view', $message);

        if ($message->sender_id === $user->id) {
            return;
        }

        $conversation = $message->conversation;
        $markedAsRead = $this->persistUnreadPeerMessagesAsRead($conversation, $user, $message);

        foreach ($markedAsRead as $readMessage) {
            $this->broadcast->broadcast(new MessageReadBroadcast($readMessage, $user));
        }
    }

    /**
     * Mark peer messages as read by $reader (up to $upToMessage when provided).
     *
     * @return list<Message>
     */
    private function persistUnreadPeerMessagesAsRead(
        Conversation $conversation,
        User $reader,
        ?Message $upToMessage,
    ): array {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereDoesntHave('reads', static function ($q) use ($reader): void {
                $q->where('user_id', $reader->id);
            });

        if ($upToMessage !== null) {
            $query->where('created_at', '<=', $upToMessage->created_at);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Message> $messages */
        $messages = $query->orderBy('created_at')->get();

        if ($messages->isEmpty()) {
            return [];
        }

        $readAt = now();
        $latest = $messages->last();

        DB::transaction(function () use ($messages, $reader, $conversation, $latest, $readAt): void {
            foreach ($messages as $msg) {
                MessageRead::query()->updateOrCreate(
                    [
                        'message_id' => $msg->id,
                        'user_id' => $reader->id,
                    ],
                    [
                        'read_at' => $readAt,
                    ]
                );
            }

            $participant = $conversation->participantRows()
                ->where('user_id', $reader->id)
                ->first();

            if ($participant !== null && $latest !== null) {
                $current = $participant->last_read_at;

                if ($current === null || $latest->created_at->greaterThan($current)) {
                    $participant->last_read_at = $latest->created_at;
                    $participant->save();
                }
            }
        });

        return $messages->all();
    }

    /**
     * If any other participant is online, mark the outgoing message as delivered (double tick).
     */
    private function markDeliveredWhenRecipientsOnline(
        Message $message,
        User $sender,
        Conversation $conversation,
    ): void {
        $recipientIds = $conversation->participantRows()
            ->where('user_id', '!=', $sender->id)
            ->pluck('user_id')
            ->map(static fn($id): int => (int) $id)
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        if ($this->presence->getOnlineUsers($recipientIds)->isEmpty()) {
            return;
        }

        $message->forceFill(['status' => MessageStatus::Delivered]);
        $this->messages->save($message);
    }

    public function broadcastTyping(string $conversationUuid, User $user, bool $isTyping): void
    {
        $conversation = $this->conversations->findByUuidForUser($conversationUuid, $user);

        if ($conversation === null) {
            throw ConversationNotFoundException::forUuid($conversationUuid);
        }

        Gate::forUser($user)->authorize('view', $conversation);

        $this->broadcast->broadcast(new UserTyping($conversation->id, $user, $isTyping));
    }
}
