<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'ErrorResponse',
    description: "The app's standard failure envelope, returned by the sendResponse() helper.",
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Something went wrong. Please try again.'),
        new OA\Property(property: 'data', nullable: true),
    ],
    type: 'object',
)]
class ErrorResponseSchema {}
