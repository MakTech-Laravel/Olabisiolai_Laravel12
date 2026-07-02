<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Subscription',
    title: 'Subscription',
    description: 'A vendor business\'s subscription/premium status (see SubscriptionService::subscriptionPayload).',
    properties: [
        new OA\Property(property: 'plan', type: 'string', enum: ['free', 'premium']),
        new OA\Property(property: 'plan_label', type: 'string', example: 'Premium'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'pending_payment', 'expired']),
        new OA\Property(property: 'status_label', type: 'string', example: 'Active'),
        new OA\Property(property: 'expires_at', type: 'string', nullable: true),
        new OA\Property(property: 'expires_at_iso', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'is_expired', type: 'boolean'),
        new OA\Property(property: 'days_remaining', type: 'integer'),
        new OA\Property(property: 'requires_payment', type: 'boolean'),
        new OA\Property(property: 'can_pay_premium', type: 'boolean'),
        new OA\Property(property: 'is_premium_active', type: 'boolean'),
        new OA\Property(property: 'can_access_features', type: 'boolean'),
        new OA\Property(property: 'photo_limit', type: 'integer'),
        new OA\Property(property: 'free_photo_limit', type: 'integer'),
        new OA\Property(property: 'premium_photo_limit', type: 'integer'),
        new OA\Property(property: 'is_verified', type: 'boolean'),
        new OA\Property(property: 'can_boost', type: 'boolean'),
        new OA\Property(property: 'analytics_locked', type: 'boolean'),
    ],
    type: 'object',
)]
class SubscriptionSchema {}
