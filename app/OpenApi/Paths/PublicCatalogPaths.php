<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class PublicCatalogPaths
{
    #[OA\Get(
    path: '/v1/catalog/home',
    summary: 'Get Home',
    tags: [
        'Public',
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Home successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 500,
        description: 'Unexpected server error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
    ],
)]
    private function opGETCatalogHome_f252a4(): void {}
}
