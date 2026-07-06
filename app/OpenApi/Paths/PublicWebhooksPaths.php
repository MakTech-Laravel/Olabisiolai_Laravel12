<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class PublicWebhooksPaths
{
    #[OA\Post(
    path: '/v1/webhooks/paystack',
    summary: 'Submit Paystack',
    tags: [
        'Public',
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Paystack successfully',
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
    private function opPOSTWebhooksPaystack_495e3f(): void {}
}
