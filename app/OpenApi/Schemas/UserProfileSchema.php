<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserProfile',
    title: 'UserProfile',
    description: 'The profile payload returned by /v1/user/profile and /v1/user/settings (see UserSettingsController::profilePayload()). Distinct from the User schema used elsewhere.',
    properties: [
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'wants_marketing_emails', type: 'boolean'),
        new OA\Property(property: 'location', type: 'string', nullable: true),
        new OA\Property(property: 'image_path', type: 'string', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true),
        new OA\Property(property: 'phone_verified_at', type: 'string', nullable: true),
        new OA\Property(property: 'email_verified', type: 'boolean'),
        new OA\Property(property: 'email_verification_required', type: 'boolean'),
        new OA\Property(property: 'can_make_purchases', type: 'boolean'),
        new OA\Property(property: 'followers_count', type: 'integer'),
        new OA\Property(property: 'following_count', type: 'integer'),
    ],
    type: 'object',
)]
class UserProfileSchema {}
