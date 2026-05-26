<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AdminMessagingUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proxies standard messaging endpoints for admins via a bridged {@see \App\Models\User}.
 */
final class AdminMessagingController extends Controller
{
    public function __construct(
        private readonly ConversationController $conversations,
        private readonly MessageController $messages,
        private readonly AttachmentController $attachments,
    ) {}

    public function identity(Request $request): JsonResponse
    {
        $admin = adminAuthCheck($request);

        if ($admin === null) {
            return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
        }

        $user = AdminMessagingUserResolver::resolve($admin);

        return sendResponse(true, 'Messaging identity retrieved.', [
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'user',
            ],
        ]);
    }

    public function indexConversations(Request $request): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->conversations->index($req));
    }

    public function showConversation(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->conversations->show($req, $conversation));
    }

    public function searchConversations(Request $request): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->conversations->search($req));
    }

    public function searchRecipients(Request $request): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->conversations->searchRecipients($req));
    }

    public function indexMessages(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->messages->index($req, $conversation));
    }

    public function storeMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        return $this->withMessagingUser($request, fn() => $this->messages->store($request, $conversation));
    }

    public function updateMessage(EditMessageRequest $request, Message $message): JsonResponse
    {
        return $this->withMessagingUser($request, fn() => $this->messages->update($request, $message));
    }

    public function destroyMessage(Request $request, Message $message): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->messages->destroy($req, $message));
    }

    public function markMessageRead(Request $request, Message $message): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->messages->markRead($req, $message));
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->messages->typing($req, $conversation));
    }

    public function storeAttachment(UploadAttachmentRequest $request): JsonResponse
    {
        return $this->withMessagingUser($request, fn() => $this->attachments->store($request));
    }

    public function destroyAttachment(Request $request, Attachment $attachment): JsonResponse
    {
        return $this->withMessagingUser($request, fn(Request $req) => $this->attachments->destroy($req, $attachment));
    }

    /**
     * @param  callable(Request): JsonResponse  $handler
     */
    private function withMessagingUser(Request $request, callable $handler): JsonResponse
    {
        $admin = adminAuthCheck($request);

        if ($admin === null) {
            return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
        }

        $messagingUser = AdminMessagingUserResolver::resolve($admin);
        $request->setUserResolver(static fn() => $messagingUser);

        $previousDefaultGuard = Auth::getDefaultDriver();
        $previousApiUser = Auth::guard('api')->user();

        // Controllers call $this->authorize() and $request->user('api'); both must see the bridged User.
        Auth::shouldUse('api');
        Auth::guard('api')->setUser($messagingUser);

        try {
            return $handler($request);
        } finally {
            Auth::shouldUse($previousDefaultGuard);

            if ($previousApiUser !== null) {
                Auth::guard('api')->setUser($previousApiUser);
            } else {
                Auth::guard('api')->forgetUser();
            }
        }
    }
}
