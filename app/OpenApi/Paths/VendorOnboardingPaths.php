<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class VendorOnboardingPaths
{
    #[OA\Get(
    path: '/v1/vendor/onboarding/status',
    summary: 'Get Status',
    tags: [
        'Vendors',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Status successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETVendorOnboardingStatus_1f5bf1(): void {}
}
