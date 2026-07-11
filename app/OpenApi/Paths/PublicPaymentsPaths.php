<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class PublicPaymentsPaths
{
    #[OA\Get(
    path: '/v1/payments/config',
    summary: 'Get Config',
    tags: [
        'Public',
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Config successfully',
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
    private function opGETPaymentsConfig_4e66f0(): void {}
}
