<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ValidationError',
    title: 'ValidationError',
    description: "Laravel's default 422 shape, returned when a FormRequest fails validation before "
        .'reaching controller code (uncaught ValidationException).',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
            example: ['email' => ['The email field is required.']],
        ),
    ],
    type: 'object',
)]
class ValidationErrorSchema {}
