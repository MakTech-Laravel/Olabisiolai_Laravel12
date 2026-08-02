<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemapService): Response
    {
        $path = $sitemapService->path();

        $xml = is_file($path)
            ? (string) file_get_contents($path)
            : $sitemapService->emptyUrlsetXml();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
