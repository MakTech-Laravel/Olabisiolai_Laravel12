<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\DeleteMessageAction;
use App\Actions\Messaging\EditMessageAction;
use App\Actions\Messaging\MarkAsReadAction;
use App\Actions\Messaging\SendMessageAction;
use App\Actions\Messaging\UploadAttachmentAction;
use App\DTOs\Messaging\MessageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use App\Support\MessagingHelper;
use App\Traits\HasApiResponse;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MessageController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly MessageService $messages,
        private readonly SendMessageAction $sendMessage,
        private readonly EditMessageAction $editMessage,
        private readonly DeleteMessageAction $deleteMessage,
        private readonly MarkAsReadAction $markAsRead,
        private readonly UploadAttachmentAction $uploadAttachment,
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('view', $conversation);

        $cursor = $request->query('cursor');

        $paginator = $this->messages->getMessages((string) $conversation->uuid, $user, is_string($cursor) ? $cursor : null);

        $conversation->loadMissing([
            'participantRows.user.messagingPresence',
            'participantRows.user.businessInfo:id,user_id,business_name,logo_path,verified_at',
        ]);

        $conversationName = MessagingHelper::conversationDisplayName($conversation, $user);
        $peer = MessagingHelper::conversationPeerSummary($conversation, $user);

        return $this->successWithMeta(
            [
                'conversation_name' => $conversationName,
                'conversation_uuid' => $conversation->uuid,
                'display_name' => $conversationName,
                'conversation_image_url' => $peer['avatar_url'] ?? null,
                'peer' => $peer,
                'messages' => MessageResource::collection($paginator)->resolve(),
            ],
            'Messages retrieved successfully.',
            $this->cursorPaginationMeta($paginator),
        );
    }

    /**
     * @return array{pagination: array<string, mixed>}
     */
    private function cursorPaginationMeta(CursorPaginator $paginator): array
    {
        return [
            'pagination' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('view', $conversation);

        $validated = $request->validated();

        $attachmentIds = array_map('intval', $validated['attachment_ids'] ?? []);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments', []) as $file) {
                $attachmentIds[] = $this->uploadAttachment->execute($file, $user)->id;
            }
        }

        $parentId = null;

        if (! empty($validated['parent_uuid'])) {
            $parentId = Message::query()
                ->where('uuid', $validated['parent_uuid'])
                ->where('conversation_id', $conversation->id)
                ->value('id');

            if ($parentId === null) {
                return $this->errorResponse('Invalid parent message.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                    'parent_uuid' => ['The selected parent is invalid for this conversation.'],
                ]);
            }
        }

        $dto = new MessageDTO(
            conversationUuid: (string) $conversation->uuid,
            body: $validated['body'] ?? null,
            parentId: $parentId !== null ? (int) $parentId : null,
            attachmentIds: $attachmentIds,
        );

        $message = $this->sendMessage->execute($dto, $user);

        return $this->successResponse(
            new MessageResource($message),
            'Message sent successfully.',
            Response::HTTP_CREATED,
        );
    }

    public function update(EditMessageRequest $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $updated = $this->editMessage->execute((string) $message->uuid, $request->validated('body'), $user);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Message not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            new MessageResource($updated),
            'Message updated successfully.',
        );
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $deleted = $this->deleteMessage->execute((string) $message->uuid, $user);

        if (! $deleted) {
            return $this->errorResponse('Message not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            null,
            'Message deleted successfully.',
        );
    }

    public function markRead(Request $request, Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        try {
            $this->markAsRead->execute((string) $message->uuid, $user);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Message not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            null,
            'Message marked as read.',
        );
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        $this->messages->broadcastTyping((string) $conversation->uuid, $user, $validated['is_typing']);

        return $this->successResponse(
            null,
            'Typing state broadcast.',
        );
    }
}
