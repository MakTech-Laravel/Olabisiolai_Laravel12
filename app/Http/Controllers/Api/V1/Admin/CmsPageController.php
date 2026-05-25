<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CmsPageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CmsPageResource;
use App\Services\CmsPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CmsPageController extends Controller
{
    public function __construct(private CmsPageService $cmsPageService) {}

    public function index(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $pages = $this->cmsPageService->all();

            return sendResponse(true, 'CMS pages retrieved successfully.', [
                'pages' => CmsPageResource::collection($pages),
                'count' => $pages->count(),
                'available_types' => collect(CmsPageType::cases())->map(fn(CmsPageType $type) => [
                    'type' => $type->value,
                    'label' => $type->label(),
                ])->values(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function view(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'type' => ['required', 'string', Rule::in(CmsPageType::values())],
            ]);

            $type = CmsPageType::from($validated['type']);
            $page = $this->cmsPageService->getByType($type);

            if ($page === null) {
                return sendResponse(true, 'CMS page not found for this type.', [
                    'page' => null,
                    'type' => $type->value,
                    'type_label' => $type->label(),
                ]);
            }

            return sendResponse(true, 'CMS page retrieved successfully.', [
                'page' => new CmsPageResource($page),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function uploadImage(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            ]);

            $path = $validated['image']->store('cms', 'public');
            $url = Storage::disk('public')->url($path);

            return sendResponse(true, 'Image uploaded successfully.', [
                'url' => $url,
                'path' => $path,
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function upsert(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'type' => ['required', 'string', Rule::in(CmsPageType::values())],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
            ]);

            $type = CmsPageType::from($validated['type']);
            $existing = $this->cmsPageService->getByType($type);

            $page = $this->cmsPageService->upsertByType(
                $type,
                $validated['title'],
                $validated['description']
            );

            $message = $existing === null
                ? 'CMS page created successfully.'
                : 'CMS page updated successfully.';

            $statusCode = $existing === null
                ? Response::HTTP_CREATED
                : Response::HTTP_OK;

            return sendResponse(true, $message, [
                'page' => new CmsPageResource($page),
                'is_created' => $existing === null,
            ], $statusCode);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
