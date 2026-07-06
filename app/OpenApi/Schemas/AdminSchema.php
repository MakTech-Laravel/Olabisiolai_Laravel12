<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Admin',
    title: 'Admin',
    description: 'An admin backoffice account (see AdminResource).',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'image', type: 'string', nullable: true),
        new OA\Property(property: 'is_super_admin', type: 'boolean'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', nullable: true),
    ],
    type: 'object',
)]
class AdminSchema {}
