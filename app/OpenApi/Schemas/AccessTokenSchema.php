<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AccessToken',
    title: 'AccessToken',
    description: 'A Passport personal access token string, as returned by AuthService::issueAccessToken(). '
        .'Send it back as `Authorization: Bearer <token>`.',
    type: 'string',
    example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoi...',
)]
class AccessTokenSchema {}
