<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Services\AdminMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VendorAdminChatController extends Controller
{
    public function __construct(
        private readonly AdminMessagingService $adminMessaging,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $vendor */
        $vendor = $request->user('api');

        $conversation = $this->adminMessaging->resolveVendorAdminConversation($vendor);
        $conversation->load([
            'participantRows.user',
            'lastMessage.sender',
        ]);

        return sendResponse(true, 'Admin conversation retrieved.', [
            'conversation' => new ConversationResource($conversation),
        ]);
    }
}
