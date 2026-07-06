<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Gidira API',
    version: '1.0.0',
    description: "REST API for the Gidira marketplace, consumed by the web and mobile clients.\n\n"
        .'Authentication is handled by Laravel Passport: clients obtain a personal access token from '
        .'`POST /api/v1/auth/login` (or the OTP/2FA/device-verification steps that follow it) and send it as '
        ."`Authorization: Bearer <token>` on subsequent requests.\n\n"
        .'Successful responses are wrapped as `{"success": true, "message": string, "data": ...}`. '
        .'Failed responses are wrapped as `{"success": false, "message": string, "data": ...|null}`. '
        .'Validation failures that are not caught by the controller fall back to Laravel\'s default shape: '
        .'`{"message": string, "errors": {field: string[]}}` with HTTP 422.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'API server')]
#[OA\SecurityScheme(
    securityScheme: 'passport',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Passport Personal Access Token',
    description: 'Laravel Passport-issued personal access token. Obtain one via POST /api/v1/auth/login '
        .'(user/vendor), POST /api/v1/admin/login (admin), or the OTP/2FA verification endpoints that '
        .'complete a login challenge. Send as: Authorization: Bearer <token>.',
)]
class BaseInfo {}
