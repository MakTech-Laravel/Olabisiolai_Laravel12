<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\SeoPageService;
use App\Services\SeoResolverService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicSeoPageController extends Controller
{
    public function __construct(
        private readonly SeoPageService $seoPages,
        private readonly SeoResolverService $resolver,
    ) {}

    /**
     * Legacy thin lookup — prefer resolve(). Kept for older clients.
     */
    public function byPath(Request $request)
    {
        try {
            $raw = $request->query('path');
            $page = $this->seoPages->findByPath(is_string($raw) ? $raw : null);

            if ($page === null) {
                return sendResponse(false, 'SEO page not found for this path.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'SEO page retrieved successfully.', [
                'path' => $page->path,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'meta_keywords' => $page->meta_keywords,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resolve(Request $request)
    {
        try {
            $raw = $request->query('path');
            $seo = $this->resolver->resolve(is_string($raw) ? $raw : '/');

            return sendResponse(true, 'SEO resolved successfully.', $seo->toArray());
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
