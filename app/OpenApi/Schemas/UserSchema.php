<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    title: 'User',
    description: 'A marketplace user or vendor account (see UserResource).',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 101),
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'first_name', type: 'string', example: 'Ada'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Obi'),
        new OA\Property(property: 'name', type: 'string', example: 'Ada Obi'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'ada@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+2348012345678'),
        new OA\Property(property: 'phone_formatted', type: 'string', nullable: true, example: '+234 801 234 5678'),
        new OA\Property(property: 'location', type: 'string', nullable: true),
        new OA\Property(property: 'image_path', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'role', type: 'string', enum: ['user', 'vendor'], example: 'user'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'wants_marketing_emails', type: 'boolean'),
        new OA\Property(property: 'settings', type: 'object'),
        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, example: '02 Jul 2026, 10:00 AM'),
        new OA\Property(property: 'phone_verified_at', type: 'string', nullable: true),
        new OA\Property(property: 'email_verified', type: 'boolean'),
        new OA\Property(property: 'email_verification_required', type: 'boolean'),
        new OA\Property(property: 'can_make_purchases', type: 'boolean'),
        new OA\Property(property: 'account_verified', type: 'boolean'),
        new OA\Property(property: 'verification_channel', type: 'string', enum: ['email', 'phone'], nullable: true),
        new OA\Property(property: 'created_at', type: 'string', nullable: true),
    ],
    type: 'object',
)]
class UserSchema {}
