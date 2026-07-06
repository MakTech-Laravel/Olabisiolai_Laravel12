<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\CmsPageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CmsPageResource;
use App\Services\CmsPageService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

class PublicCmsPageController extends Controller
{
    public function __construct(private CmsPageService $cmsPageService) {}

    #[OA\Get(
        path: '/v1/about',
        summary: 'Get the "About" CMS page',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'Page retrieved successfully', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'page', type: 'object')], type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'CMS page not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    #[OA\Get(
        path: '/v1/privacy-policy',
        summary: 'Get the "Privacy Policy" CMS page',
        description: 'Shares the same handler/response shape as GET /v1/about.',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'Page retrieved successfully', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'page', type: 'object')], type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'CMS page not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    #[OA\Get(
        path: '/v1/terms',
        summary: 'Get the "Terms" CMS page',
        description: 'Shares the same handler/response shape as GET /v1/about.',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'Page retrieved successfully', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'page', type: 'object')], type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'CMS page not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
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
