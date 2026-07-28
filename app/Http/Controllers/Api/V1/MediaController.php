<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMediaRequest;
use App\Models\Media;
use App\Services\MediaUploadService;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class MediaController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly MediaUploadService $mediaUploads,
    ) {}

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $result = $this->mediaUploads->store(
            $request->file('file'),
            $request->uploadable(),
            $request->uploadableType(),
        );

        $status = $result['created']
            ? Response::HTTP_CREATED
            : Response::HTTP_OK;

        $message = $result['created']
            ? 'Media uploaded successfully.'
            : 'Duplicate media returned; existing record reused.';

        return $this->successResponse(
            $this->toPayload($result['media']),
            $message,
            $status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'status' => $media->status->value,
            'size_kb' => $media->sizeKb(),
            'path' => $media->path,
            'mime_type' => $media->mime_type,
            'uploadable_type' => $media->uploadable_type_key,
            'uploadable_id' => $media->uploadable_id,
            'original_filename' => $media->original_filename,
        ];
    }
}
