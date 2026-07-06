<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiResponse',
    title: 'ApiResponse',
    description: "The app's standard response envelope, returned by the sendResponse() helper.",
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'data', nullable: true),
    ],
    type: 'object',
)]
class ApiResponseSchema {}
