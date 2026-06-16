<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\CreateConversationAction;
use App\DTOs\Messaging\ConversationDTO;
use App\Enums\ConversationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageRecipientResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConversationController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly CreateConversationAction $createConversation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $filters = array_filter([
            'type' => $request->query('type'),
            'archived' => $request->boolean('archived') ? true : null,
            'unread' => $request->boolean('unread') ? true : null,
            'verified_only' => $request->boolean('verified_only') ? true : null,
            'business_info_id' => $request->query('business_info_id'),
            'inbox' => $request->query('inbox'),
        ], static fn(mixed $value): bool => $value !== null && $value !== '');

        $paginator = $this->conversations->getConversationsForUser($user, $filters, 30);

        return $this->successResponse(
            ConversationResource::collection($paginator),
            'Conversations retrieved successfully.',
        );
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('create', Conversation::class);

        $validated = $request->validated();

        /** @var list<string> $participantUuids */
        $participantUuids = array_values(array_unique(array_map(
            static fn(string $uuid): string => strtoupper(trim($uuid)),
            $validated['participants'],
        )));

        $participantUserIds = User::query()
            ->whereIn('uuid', $participantUuids)
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();

        if (count($participantUserIds) !== count($participantUuids)) {
            return $this->errorResponse(
                'One or more participant UUIDs are invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['participants' => ['Each participant must be a valid user UUID.']],
            );
        }

        if ($user->uuid !== null && in_array(strtoupper((string) $user->uuid), $participantUuids, true)) {
            return $this->errorResponse(
                'You cannot add yourself as a participant.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['participants' => ['You are already part of this conversation.']],
            );
        }

        $dto = new ConversationDTO(
            type: ConversationType::from($validated['type']),
            name: $validated['name'] ?? null,
            participantUserIds: $participantUserIds,
            businessInfoId: isset($validated['business_info_id']) ? (int) $validated['business_info_id'] : null,
        );

        $conversation = $this->createConversation->execute($dto, $user);

        return $this->successResponse(
            new ConversationResource($conversation),
            'Conversation created successfully.',
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('view', $conversation);

        $loaded = $this->conversations->getConversation((string) $conversation->uuid, $user);

        return $this->successResponse(
            new ConversationResource($loaded),
            'Conversation retrieved successfully.',
        );
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $this->authorize('delete', $conversation);

        $this->conversations->deleteConversation($conversation);

        return $this->successResponse(
            null,
            'Conversation deleted successfully.',
        );
    }

    public function search(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $results = $this->conversations->searchConversations($user, $validated['q']);

        return $this->successResponse(
            ConversationResource::collection($results),
            'Search completed successfully.',
        );
    }

    public function searchRecipients(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $results = $this->conversations->searchRecipients($user, $validated['q']);

        return $this->successResponse(
            MessageRecipientResource::collection($results),
            'Recipients retrieved successfully.',
        );
    }
}
