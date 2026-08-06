<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\SeoPage;
use App\Services\SpaShellService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpaShellController extends Controller
{
    public function __invoke(Request $request, SpaShellService $spaShell): Response
    {
        $raw = $request->query('path');
        if (! is_string($raw) || trim($raw) === '') {
            $raw = $request->headers->get('X-Original-URI')
                ?? $request->headers->get('X-Forwarded-Uri')
                ?? '/';
        }

        // X-Original-URI may include query string — keep path only.
        $pathOnly = parse_url((string) $raw, PHP_URL_PATH);
        $path = SeoPage::normalizePath(is_string($pathOnly) ? $pathOnly : (string) $raw);

        $html = $spaShell->render($path);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
            'X-Gidira-Spa-Shell' => '1',
            'X-Gidira-Spa-Path' => $path,
        ]);
    }
}
