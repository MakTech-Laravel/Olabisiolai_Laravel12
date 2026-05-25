<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\CmsPageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CmsPageResource;
use App\Services\CmsPageService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicCmsPageController extends Controller
{
    public function __construct(private CmsPageService $cmsPageService) {}

    public function show(string $slug)
    {
        try {
            $cmsType = CmsPageType::fromPublicSlug($slug);

            if ($cmsType === null) {
                throw ValidationException::withMessages([
                    'slug' => ['Invalid CMS page.'],
                ]);
            }
            $page = $this->cmsPageService->getByType($cmsType);

            if ($page === null) {
                return sendResponse(false, 'CMS page not found.', null, Response::HTTP_NOT_FOUND);
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
}
