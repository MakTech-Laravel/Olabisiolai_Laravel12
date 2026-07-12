<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCatalogDiscoveryItemResource;
use App\Services\BusinessCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Throwable;

class PublicCatalogDiscoveryController extends Controller
{
    public function __construct(private readonly BusinessCatalogService $catalogService) {}

    #[OA\Get(
        path: '/v1/catalog/home',
        summary: 'Curated premium catalog items for the homepage',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 12)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Curated catalog items retrieved'),
            new OA\Response(response: 500, description: 'Unexpected server error'),
        ],
    )]
    public function home(Request $request)
    {
        try {
            $validated = $request->validate([
                'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
            ]);

            $items = $this->catalogService->curatedPremiumHomeItems((int) ($validated['limit'] ?? 6));

            return sendResponse(true, 'Curated catalog items retrieved successfully.', [
                'items' => PublicCatalogDiscoveryItemResource::collection($items)->resolve(),
                'count' => $items->count(),
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

    #[OA\Get(
        path: '/v1/catalog',
        summary: 'Full catalog discovery feed',
        tags: ['Public'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'city', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['product', 'service'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Catalog feed retrieved'),
            new OA\Response(response: 500, description: 'Unexpected server error'),
        ],
    )]
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'city' => ['nullable', 'string', 'max:120'],
                'type' => ['nullable', 'string', Rule::in(['product', 'service'])],
                'search' => ['nullable', 'string', 'max:255'],
            ]);

            $paginator = $this->catalogService->paginateDiscoveryFeed(
                [
                    'category_id' => $validated['category_id'] ?? null,
                    'city' => isset($validated['city']) ? trim((string) $validated['city']) : null,
                    'type' => $validated['type'] ?? null,
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
                (int) ($validated['per_page'] ?? 24),
            );

            return sendResponse(true, 'Catalog feed retrieved successfully.', [
                'items' => PublicCatalogDiscoveryItemResource::collection($paginator->items())->resolve(),
                'count' => $paginator->total(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => max(1, $paginator->lastPage()),
                    'total' => $paginator->total(),
                ],
                'filter' => [
                    'category_id' => $validated['category_id'] ?? null,
                    'city' => isset($validated['city']) ? trim((string) $validated['city']) : null,
                    'type' => $validated['type'] ?? null,
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
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
