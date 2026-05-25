<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\UploadAttachmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AttachmentService;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttachmentController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly UploadAttachmentAction $uploadAttachment,
        private readonly AttachmentService $attachments,
    ) {}

    public function store(UploadAttachmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $attachment = $this->uploadAttachment->execute($request->file('file'), $user);

        return $this->successResponse(
            new AttachmentResource($attachment),
            'Attachment uploaded successfully.',
            Response::HTTP_CREATED,
        );
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $deleted = $this->attachments->delete((string) $attachment->uuid, $user);

        if (! $deleted) {
            return $this->errorResponse('Attachment not found or unauthorized.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            null,
            'Attachment deleted successfully.',
        );
    }
}
