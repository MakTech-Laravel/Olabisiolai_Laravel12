<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

class MessagingPaths
{
    #[OA\Post(
    path: '/v1/presence/ping',
    summary: 'Submit Ping',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Ping successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTPresencePing_685c00(): void {}

    #[OA\Post(
    path: '/v1/presence/offline',
    summary: 'Submit Offline',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Offline successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTPresenceOffline_eb201f(): void {}

    #[OA\Get(
    path: '/v1/conversations/search',
    summary: 'Get Search',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Search successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETConversationsSearch_8ef02b(): void {}

    #[OA\Get(
    path: '/v1/conversations/recipients/search',
    summary: 'Get Search',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Search successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETConversationsRecipientsSearch_7fdf69(): void {}

    #[OA\Get(
    path: '/v1/conversations',
    summary: 'List Conversations',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Conversations successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETConversations_a35aae(): void {}

    #[OA\Post(
    path: '/v1/conversations',
    summary: 'Create Conversations',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'type',
                'participants',
            ],
            properties: [
                new OA\Property(
                    property: 'type',
                    enum: [
                        'direct',
                        'group',
                        'channel',
                    ],
                    example: 'direct',
                    type: 'string',
                ),
                new OA\Property(
                    property: 'name',
                    type: 'string',
                    example: 'John Doe',
                    nullable: true,
                    maxLength: 255,
                ),
                new OA\Property(
                    property: 'participants',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        example: 'Example Text',
                    ),
                ),
                new OA\Property(
                    property: 'business_info_id',
                    type: 'integer',
                    example: 1,
                    nullable: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Conversations successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTConversations_ca8f59(): void {}

    #[OA\Get(
    path: '/v1/conversations/{conversation}',
    summary: 'Get Conversations',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'conversation',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Get Conversations successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETConversationsconversation_0b31dd(): void {}

    #[OA\Delete(
    path: '/v1/conversations/{conversation}',
    summary: 'Delete Conversations',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'conversation',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Delete Conversations successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opDELETEConversationsconversation_a87e6f(): void {}

    #[OA\Get(
    path: '/v1/conversations/{conversation}/messages',
    summary: 'List Messages',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'conversation',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'List Messages successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opGETConversationsconversationMessages_bdcf31(): void {}

    #[OA\Post(
    path: '/v1/conversations/{conversation}/messages',
    summary: 'Create Messages',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'conversation',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                new OA\Property(
                    property: 'body',
                    type: 'string',
                    example: 'Example description text.',
                    nullable: true,
                    maxLength: 10000,
                ),
                new OA\Property(
                    property: 'attachments',
                    description: 'One or more image files',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        format: 'binary',
                    ),
                    nullable: true,
                ),
                new OA\Property(
                    property: 'attachment_ids',
                    type: 'array',
                    items: new OA\Items(
                        type: 'integer',
                        example: 10,
                    ),
                ),
                new OA\Property(
                    property: 'parent_uuid',
                    type: 'string',
                    format: 'uuid',
                    example: '550e8400-e29b-41d4-a716-446655440000',
                    nullable: true,
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Messages successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTConversationsconversationMessages_cb1172(): void {}

    #[OA\Patch(
    path: '/v1/messages/{message}',
    summary: 'Update Messages',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'message',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'body',
            ],
            properties: [
                new OA\Property(
                    property: 'body',
                    type: 'string',
                    example: 'Example description text.',
                    minLength: 1,
                    maxLength: 10000,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Update Messages successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPATCHMessagesmessage_38137d(): void {}

    #[OA\Delete(
    path: '/v1/messages/{message}',
    summary: 'Delete Messages',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'message',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Delete Messages successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opDELETEMessagesmessage_50c36a(): void {}

    #[OA\Post(
    path: '/v1/messages/{message}/read',
    summary: 'Submit Read',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'message',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Read successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTMessagesmessageRead_c6c431(): void {}

    #[OA\Post(
    path: '/v1/conversations/{conversation}/typing',
    summary: 'Submit Typing',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'conversation',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: [
                'is_typing',
            ],
            properties: [
                new OA\Property(
                    property: 'is_typing',
                    type: 'boolean',
                    example: true,
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
        response: 200,
        description: 'Submit Typing successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTConversationsconversationTyping_9ae8c7(): void {}

    #[OA\Post(
    path: '/v1/attachments',
    summary: 'Create Attachments',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: [
                    'file',
                ],
                properties: [
                new OA\Property(
                    property: 'file',
                    description: 'File upload',
                    type: 'string',
                    format: 'binary',
                ),
            ],
            ),
        ),
    ),
    responses: [
        new OA\Response(
        response: 201,
        description: 'Create Attachments successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opPOSTAttachments_a9e09b(): void {}

    #[OA\Delete(
    path: '/v1/attachments/{attachment}',
    summary: 'Delete Attachments',
    tags: [
        'Messaging',
    ],
    security: [
        [
            'passport' => [],
        ],
    ],
    parameters: [
        new OA\Parameter(
        name: 'attachment',
        in: 'path',
        required: true,
        schema: new OA\Schema(
        type: 'integer',
    ),
        example: 1,
    ),
    ],
    responses: [
        new OA\Response(
        response: 200,
        description: 'Delete Attachments successfully',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ApiResponse',
        ),
    ),
        new OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
        ),
    ),
        new OA\Response(
        response: 404,
        description: 'Not found',
        content: new OA\JsonContent(
            ref: '#/components/schemas/ErrorResponse',
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
    private function opDELETEAttachmentsattachment_51a7d8(): void {}
}
