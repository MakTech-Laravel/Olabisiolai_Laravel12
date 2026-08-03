<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSeoPageRequest;
use App\Http\Resources\Api\V1\SeoPageResource;
use App\Models\SeoPage;
use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SeoPageController extends Controller
{
    public function __construct(private readonly SitemapService $sitemapService) {}

    public function index(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $search = trim((string) ($validated['search'] ?? ''));
            $perPage = (int) ($validated['per_page'] ?? 20);

            $query = SeoPage::query()->orderBy('path');

            if ($search !== '') {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('path', 'like', "%{$search}%")
                        ->orWhere('page_name', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%");
                });
            }

            $paginator = $query->paginate($perPage);

            return sendResponse(true, 'SEO pages retrieved successfully.', [
                'pages' => SeoPageResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
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

    public function view(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:seo_pages,id'],
            ]);

            $page = SeoPage::query()->findOrFail($validated['id']);

            return sendResponse(true, 'SEO page retrieved successfully.', [
                'page' => new SeoPageResource($page),
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

    public function update(UpdateSeoPageRequest $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validated();
            $page = SeoPage::query()->findOrFail($validated['id']);

            $page->update([
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
            ]);

            return sendResponse(true, 'SEO page updated successfully.', [
                'page' => new SeoPageResource($page->fresh()),
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

    public function generateSitemap(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $lock = Cache::lock('sitemap:generate', 120);

            if (! $lock->get()) {
                return sendResponse(
                    false,
                    'Sitemap generation is already in progress. Please try again shortly.',
                    null,
                    Response::HTTP_TOO_MANY_REQUESTS
                );
            }

            try {
                $result = $this->sitemapService->generate();
            } finally {
                $lock->release();
            }

            return sendResponse(true, 'Sitemap generated successfully.', [
                'path' => $result['path'],
                'urls' => $result['urls'],
                'chunks' => $result['chunks'],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Failed to generate sitemap. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
