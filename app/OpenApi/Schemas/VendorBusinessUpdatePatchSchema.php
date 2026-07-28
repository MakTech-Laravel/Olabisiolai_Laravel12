<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Partial vendor business update body (JSON).
 * Omit a field to keep the current value; send null/empty on nullable fields to clear.
 */
#[OA\Schema(
    schema: 'VendorBusinessUpdatePatch',
    title: 'VendorBusinessUpdatePatch',
    description: 'Partial update payload. Only include fields you want to change. '
        .'Omitted fields are left unchanged. Explicit null (or empty string) clears nullable fields '
        .'(website, whatsapp, street_address, google_place_id, etc.).',
    type: 'object',
    properties: [
        new OA\Property(property: 'business_id', type: 'integer', example: 1, description: 'Target business owned by the vendor. Omit to use the active business.'),
        new OA\Property(property: 'location_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'category_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'subcategory', type: 'string', example: 'Home cleaning', nullable: true, maxLength: 255),
        new OA\Property(property: 'business_name', type: 'string', example: 'Acme Services', nullable: true, maxLength: 255),
        new OA\Property(property: 'full_address', type: 'string', example: '12 Adeola Odeku Street, Victoria Island', nullable: true, maxLength: 500, description: 'Alias for street_address.'),
        new OA\Property(property: 'street_address', type: 'string', example: '12 Adeola Odeku Street, Victoria Island', nullable: true, maxLength: 500),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 6.4281, nullable: true),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 3.4219, nullable: true),
        new OA\Property(property: 'google_place_id', type: 'string', example: 'ChIJxxxxx', nullable: true, maxLength: 255),
        new OA\Property(property: 'business_description', type: 'string', example: 'Professional home and office services.', nullable: true, maxLength: 150),
        new OA\Property(
            property: 'services',
            type: 'array',
            nullable: true,
            items: new OA\Items(type: 'string', example: 'Repairs'),
            minItems: 1,
        ),
        new OA\Property(property: 'phone', type: 'string', example: '+2348012345678', nullable: true, maxLength: 30),
        new OA\Property(property: 'whatsapp', type: 'string', example: '+2348012345678', nullable: true, maxLength: 30),
        new OA\Property(property: 'website', type: 'string', format: 'uri', example: 'https://example.com', nullable: true, maxLength: 2048, description: 'Send null to clear.'),
        new OA\Property(
            property: 'social_accounts',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                type: 'object',
                required: ['platform', 'url'],
                properties: [
                    new OA\Property(
                        property: 'platform',
                        type: 'string',
                        enum: ['instagram', 'facebook', 'x', 'linkedin', 'tiktok', 'youtube', 'pinterest', 'threads', 'snapchat'],
                        example: 'instagram',
                    ),
                    new OA\Property(property: 'url', type: 'string', example: 'https://instagram.com/acme', maxLength: 2048),
                ],
            ),
        ),
        new OA\Property(
            property: 'business_hours',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                type: 'object',
                required: ['day', 'is_closed'],
                properties: [
                    new OA\Property(
                        property: 'day',
                        type: 'string',
                        enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                        example: 'monday',
                    ),
                    new OA\Property(property: 'is_closed', type: 'boolean', example: false),
                    new OA\Property(property: 'is_24_hours', type: 'boolean', example: false),
                    new OA\Property(property: 'opens_at', type: 'string', example: '09:00', nullable: true),
                    new OA\Property(property: 'closes_at', type: 'string', example: '17:00', nullable: true),
                ],
            ),
        ),
        new OA\Property(
            property: 'keep_cover_paths',
            type: 'array',
            nullable: true,
            description: 'Existing cover paths to retain. Only send when updating the gallery.',
            items: new OA\Items(type: 'string', example: 'businesses/1/covers/cover1.jpg'),
        ),
    ],
    example: [
        'business_id' => 1,
        'business_name' => 'New Business Name',
    ],
)]
class VendorBusinessUpdatePatchSchema {}
