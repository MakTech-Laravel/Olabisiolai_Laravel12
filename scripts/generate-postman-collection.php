<?php

/**
 * Generate Postman collection from Laravel route:list JSON.
 * Usage: php scripts/generate-postman-collection.php [output-path]
 */
$outputPath = $argv[1] ?? dirname(__DIR__).DIRECTORY_SEPARATOR.'Olabisiolai Local.postman_collection.json';

$routesJson = shell_exec('php artisan route:list --path=api/v1 --json');
if (! $routesJson) {
    fwrite(STDERR, "Failed to run route:list\n");
    exit(1);
}

$routes = json_decode($routesJson, true);
if (! is_array($routes)) {
    fwrite(STDERR, "Invalid route JSON\n");
    exit(1);
}

/** @var array<string, array{method: string, uri: string, name: string, action: string, middleware: array}> */
$normalized = [];

foreach ($routes as $route) {
    $uri = $route['uri'] ?? '';
    if (! str_starts_with($uri, 'api/v1/')) {
        continue;
    }

    $path = substr($uri, strlen('api/v1/'));
    $methods = explode('|', $route['method'] ?? 'GET');
    $methods = array_values(array_filter($methods, fn ($m) => ! in_array($m, ['HEAD'], true)));

    $writeMethods = array_values(array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']));
    $readMethods = array_values(array_diff($methods, $writeMethods));

    foreach ($writeMethods as $writeMethod) {
        $alt = array_values(array_diff($writeMethods, [$writeMethod]));
        addNormalizedRoute($normalized, $writeMethod, $path, $route, $alt);
    }

    if ($readMethods !== [] && $writeMethods === []) {
        $primaryRead = pickPrimaryMethod($readMethods, $path);
        $alt = array_values(array_diff($readMethods, [$primaryRead]));
        addNormalizedRoute($normalized, $primaryRead, $path, $route, $alt);
    }
}

function addNormalizedRoute(array &$normalized, string $method, string $path, array $route, array $alt): void
{
    $key = strtoupper($method).' '.$path;
    if (isset($normalized[$key])) {
        return;
    }

    $normalized[$key] = [
        'method' => $method,
        'path' => $path,
        'name' => $route['name'] ?? '',
        'action' => $route['action'] ?? '',
        'middleware' => $route['middleware'] ?? [],
        'alt_methods' => $alt,
    ];
}

uksort($normalized, fn ($a, $b) => strcmp($a, $b));

$folders = [
    '0. Quick Start' => [],
    '1. Public' => [],
    '2. Auth' => [],
    '3. User' => [],
    '4. Vendor' => [],
    '5. Admin' => [],
    '6. Messaging' => [],
    '7. Notifications & Realtime' => [],
];

$quickStartPaths = [
    'auth/login',
    'auth/register',
    'admin/login',
    'auth/admin/login',
];

foreach ($normalized as $route) {
    $folder = resolveFolder($route['path'], $route['middleware']);
    if (in_array($route['path'], $quickStartPaths, true)) {
        $folders['0. Quick Start'][] = $route;
    } else {
        $folders[$folder][] = $route;
    }
}

$collection = [
    'info' => [
        '_postman_id' => '094a96c6-e4a7-42ac-82a4-b9054b026a04',
        'name' => 'Olabisiolai Local',
        'description' => buildCollectionDescription(),
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api/v1', 'type' => 'string'],
        ['key' => 'user_token', 'value' => '', 'type' => 'string'],
        ['key' => 'admin_token', 'value' => '', 'type' => 'string'],
        ['key' => 'user_email', 'value' => 'user@example.com', 'type' => 'string'],
        ['key' => 'user_password', 'value' => 'password123', 'type' => 'string'],
        ['key' => 'admin_email', 'value' => 'superadmin@dev.com', 'type' => 'string'],
        ['key' => 'admin_password', 'value' => 'superadmin@dev.com', 'type' => 'string'],
        ['key' => 'conversation_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'message_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'business_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'review_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'id', 'value' => '1', 'type' => 'string'],
    ],
    'item' => [],
];

foreach ($folders as $folderName => $folderRoutes) {
    if ($folderRoutes === []) {
        continue;
    }

    $subfolders = [];
    foreach ($folderRoutes as $route) {
        $sub = resolveSubfolder($route['path'], $folderName);
        $subfolders[$sub][] = $route;
    }

    $folderItem = ['name' => $folderName, 'item' => []];

    foreach ($subfolders as $subName => $subRoutes) {
        $subItem = ['name' => $subName, 'item' => []];
        foreach ($subRoutes as $route) {
            $subItem['item'][] = buildRequestItem($route);
        }
        $folderItem['item'][] = $subItem;
    }

    $collection['item'][] = $folderItem;
}

$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}

if (file_put_contents($outputPath, $json) === false) {
    fwrite(STDERR, "Failed to write {$outputPath}\n");
    exit(1);
}

fwrite(STDOUT, 'Wrote '.count($normalized)." routes to {$outputPath}\n");

function pickPrimaryMethod(array $methods, string $path): string
{
    $order = ['POST', 'PUT', 'PATCH', 'DELETE', 'GET'];
    foreach ($order as $method) {
        if (in_array($method, $methods, true)) {
            return $method;
        }
    }

    return $methods[0] ?? 'GET';
}

function resolveFolder(string $path, array $middleware): string
{
    if (str_starts_with($path, 'notifications') || str_starts_with($path, 'realtime')) {
        return '7. Notifications & Realtime';
    }

    if (str_starts_with($path, 'conversations') || str_starts_with($path, 'messages')
        || str_starts_with($path, 'attachments') || str_starts_with($path, 'presence')
        || str_starts_with($path, 'admin/messaging')) {
        return '6. Messaging';
    }

    if (str_starts_with($path, 'admin/')) {
        return '5. Admin';
    }

    if (str_starts_with($path, 'vendor/')) {
        return '4. Vendor';
    }

    if (str_starts_with($path, 'user/')) {
        return '3. User';
    }

    if (str_starts_with($path, 'auth/') || $path === 'admin/login' || $path === 'admin/logout' || $path === 'admin/me') {
        return '2. Auth';
    }

    return '1. Public';
}

function resolveSubfolder(string $path, string $folder): string
{
    $segments = explode('/', $path);
    $first = $segments[0];

    if ($folder === '5. Admin' && $first === 'admin') {
        $second = $segments[1] ?? 'general';

        return titleCase(str_replace('-', ' ', $second));
    }

    if ($folder === '4. Vendor' && $first === 'vendor') {
        $second = $segments[1] ?? 'general';

        return titleCase(str_replace('-', ' ', $second));
    }

    if ($folder === '3. User' && $first === 'user') {
        $second = $segments[1] ?? 'general';

        return titleCase(str_replace('-', ' ', $second));
    }

    if ($folder === '0. Quick Start') {
        if (str_contains($path, 'admin')) {
            return 'Admin Auth';
        }
        if (str_contains($path, 'register')) {
            return 'Registration';
        }

        return 'User Auth';
    }

    if ($folder === '2. Auth') {
        if (str_starts_with($path, 'auth/social')) {
            return 'Social';
        }
        if (str_contains($path, 'admin')) {
            return 'Admin Auth';
        }
        if (str_contains($path, 'forgot') || str_contains($path, 'reset')) {
            return 'Password Reset';
        }
        if (str_contains($path, 'phone')) {
            return 'Phone Login';
        }
        if (str_contains($path, 'two-factor') || str_contains($path, 'device')) {
            return '2FA & Device';
        }

        return 'User Auth';
    }

    if ($folder === '1. Public') {
        if (str_starts_with($path, 'businesses')) {
            return 'Businesses';
        }
        if (str_starts_with($path, 'categories')) {
            return 'Categories';
        }
        if (in_array($path, ['terms', 'privacy-policy', 'about'], true)) {
            return 'CMS Pages';
        }
        if (str_starts_with($path, 'reviews')) {
            return 'Reviews';
        }

        return titleCase(str_replace('-', ' ', $first));
    }

    if ($folder === '6. Messaging') {
        if (str_starts_with($path, 'admin/messaging')) {
            return 'Admin Messaging';
        }
        if (str_starts_with($path, 'conversations')) {
            return 'Conversations';
        }
        if (str_starts_with($path, 'messages') || str_starts_with($path, 'attachments')) {
            return 'Messages';
        }

        return 'Presence';
    }

    return 'General';
}

function titleCase(string $value): string
{
    return ucwords($value);
}

function buildCollectionDescription(): string
{
    return <<<'DESC'
Olabisiolai / Gidira API (Laravel 12) - local development collection.

## Setup
1. Set `base_url` (default: `http://localhost:8000/api/v1`).
2. Run **User Login** or **Admin Login** under `0. Quick Start` - tokens auto-save to `user_token` / `admin_token`.
3. Authenticated requests use Bearer token automatically.

## Auth notes
- **User/Vendor endpoints** use `user_token` (from `auth/login`).
- **Admin endpoints** use `admin_token` (from `admin/login` or `auth/admin/login`).
- Some vendor routes require active subscription (`vendor.subscription` middleware).

## Variables
| Variable | Purpose |
|----------|---------|
| base_url | API root including `/api/v1` |
| user_token | Bearer token for user/vendor |
| admin_token | Bearer token for admin panel |
| conversation_id, message_id, business_id, review_id, id | Path placeholders |

Generated from `php artisan route:list`. Re-run `scripts/generate-postman-collection.php` after route changes.
DESC;
}

function buildRequestItem(array $route): array
{
    $name = buildRequestName($route);
    $auth = resolveAuth($route);
    $body = exampleBody($route);
    $events = loginTestScript($route);

    $pathSegments = explode('/', $route['path']);
    $urlPath = array_map(fn ($seg) => pathSegmentToVariable($seg), $pathSegments);

    $item = [
        'name' => $name,
        'request' => [
            'method' => $route['method'],
            'header' => defaultHeaders($route, $body !== null),
            'url' => [
                'raw' => '{{base_url}}/'.$route['path'],
                'host' => ['{{base_url}}'],
                'path' => $urlPath,
            ],
            'description' => buildRequestDescription($route),
        ],
        'response' => [],
    ];

    if ($auth) {
        $item['request']['auth'] = $auth;
    }

    if ($body !== null) {
        $item['request']['body'] = [
            'mode' => 'raw',
            'raw' => $body,
            'options' => ['raw' => ['language' => 'json']],
        ];
    }

    if ($events) {
        $item['event'] = $events;
    }

    return $item;
}

function buildRequestName(array $route): string
{
    $friendly = [
        'auth/login' => 'User Login',
        'auth/register' => 'User Register',
        'auth/logout' => 'User Logout',
        'auth/profile' => 'Auth Profile',
        'admin/login' => 'Admin Login',
        'auth/admin/login' => 'Admin Login (auth prefix)',
        'admin/me' => 'Admin Me',
        'admin/logout' => 'Admin Logout',
    ];

    if (isset($friendly[$route['path']])) {
        return $friendly[$route['path']];
    }

    if ($route['name'] && ! str_ends_with($route['name'], '.')) {
        $parts = explode('.', $route['name']);
        $last = end($parts);
        if ($last && $last !== 'api' && $last !== 'v1') {
            return strtoupper($route['method']).' - '.titleCase(str_replace(['-', '_'], ' ', $last));
        }
    }

    $segments = explode('/', $route['path']);
    $label = implode(' / ', array_map(fn ($s) => titleCase(str_replace(['{', '}'], '', str_replace('-', ' ', $s))), $segments));

    return strtoupper($route['method']).' - '.$label;
}

function buildRequestDescription(array $route): string
{
    $lines = [];
    if ($route['name']) {
        $lines[] = '**Route name:** `'.$route['name'].'`';
    }
    if ($route['action']) {
        $lines[] = '**Controller:** `'.$route['action'].'`';
    }
    if (! empty($route['alt_methods'])) {
        $lines[] = '**Also supports:** '.implode(', ', $route['alt_methods']);
    }
    $lines[] = '**Path:** `/'.$route['path'].'`';

    return implode("\n\n", $lines);
}

function resolveAuth(array $route): ?array
{
    $middleware = implode(',', $route['middleware']);

    if (str_contains($middleware, 'auth:admin_api') && ! isPublicAuthRoute($route['path'])) {
        return bearerAuth('admin_token');
    }

    if (str_contains($middleware, 'auth:api') || (str_contains($middleware, 'auth:api,admin_api') && ! isPublicAuthRoute($route['path']))) {
        if (str_starts_with($route['path'], 'admin/')) {
            return bearerAuth('admin_token');
        }

        return bearerAuth('user_token');
    }

    return null;
}

function isPublicAuthRoute(string $path): bool
{
    $public = [
        'auth/login', 'auth/register', 'auth/otp/verify', 'auth/register/resend-otp',
        'auth/forgot-password', 'auth/forgot-password/resend-otp', 'auth/forgot-password/verify-otp',
        'auth/forgot-password/verify-token', 'auth/reset-password', 'auth/admin/login',
        'auth/two-factor/verify', 'auth/two-factor/resend-otp', 'auth/admin/two-factor/verify',
        'auth/admin/two-factor/resend-otp', 'auth/phone/request-otp', 'auth/phone/verify-otp',
        'auth/phone/resend-otp', 'auth/device/verify-otp', 'auth/device/resend-otp',
        'admin/login', 'auth/social/providers', 'auth/social/{provider}/redirect',
        'auth/social/{provider}/login', 'auth/social/{provider}/callback',
    ];

    foreach ($public as $p) {
        if ($path === $p || str_starts_with($path, 'auth/social/')) {
            return true;
        }
    }

    return false;
}

function bearerAuth(string $var): array
{
    return [
        'type' => 'bearer',
        'bearer' => [
            ['key' => 'token', 'value' => '{{'.$var.'}}', 'type' => 'string'],
        ],
    ];
}

function defaultHeaders(array $route, bool $hasJsonBody): array
{
    $headers = [
        ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
    ];

    if ($hasJsonBody) {
        $headers[] = ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'];
    }

    return $headers;
}

function pathSegmentToVariable(string $segment): string
{
    if (preg_match('/^\{(.+)\}$/', $segment, $m)) {
        $param = $m[1];
        $map = [
            'conversation' => 'conversation_id',
            'message' => 'message_id',
            'businessId' => 'business_id',
            'businessInfo' => 'business_id',
            'businessReport' => 'id',
            'reviewReport' => 'id',
            'review' => 'review_id',
            'contactMessage' => 'id',
            'catalogItem' => 'id',
            'paymentMethod' => 'id',
            'payment' => 'id',
            'attachment' => 'id',
            'reply' => 'id',
            'provider' => 'google',
            'category' => 'id',
        ];

        return '{{'.($map[$param] ?? 'id').'}}';
    }

    return $segment;
}

function loginTestScript(array $route): ?array
{
    $path = $route['path'];

    if ($path === 'auth/login') {
        return testScript('user_token');
    }
    if ($path === 'admin/login' || $path === 'auth/admin/login') {
        return testScript('admin_token');
    }

    return null;
}

function testScript(string $var): array
{
    return [[
        'listen' => 'test',
        'script' => [
            'type' => 'text/javascript',
            'exec' => [
                'if (pm.response.code === 200 || pm.response.code === 201) {',
                '    const json = pm.response.json();',
                '    const token = json.token || json.data?.token || json.access_token;',
                '    if (token) {',
                "        pm.collectionVariables.set('{$var}', token);",
                "        console.log('{$var} saved');",
                '    }',
                '}',
            ],
        ],
    ]];
}

function exampleBody(array $route): ?string
{
    if (! in_array($route['method'], ['POST', 'PUT', 'PATCH'], true)) {
        return null;
    }

    $path = $route['path'];
    $bodies = getExampleBodies();

    if (isset($bodies[$path])) {
        return $bodies[$path];
    }

    // Pattern match for parameterized paths
    foreach ($bodies as $pattern => $body) {
        if (str_contains($pattern, '{') && routePatternMatch($pattern, $path)) {
            return $body;
        }
    }

    return "{\n  \n}";
}

function routePatternMatch(string $pattern, string $path): bool
{
    $regex = '#^'.preg_replace('#\{[^}]+\}#', '[^/]+', $pattern).'$#';

    return (bool) preg_match($regex, $path);
}

function getExampleBodies(): array
{
    return [
        'auth/register' => "{\n  \"name\": \"John Doe\",\n  \"email\": \"{{user_email}}\",\n  \"password\": \"{{user_password}}\",\n  \"password_confirmation\": \"{{user_password}}\",\n  \"phone\": \"+2348012345678\"\n}",
        'auth/login' => "{\n  \"email\": \"{{user_email}}\",\n  \"password\": \"{{user_password}}\"\n}",
        'auth/otp/verify' => "{\n  \"email\": \"{{user_email}}\",\n  \"otp\": \"123456\"\n}",
        'auth/admin/login' => "{\n  \"email\": \"{{admin_email}}\",\n  \"password\": \"{{admin_password}}\"\n}",
        'admin/login' => "{\n  \"email\": \"{{admin_email}}\",\n  \"password\": \"{{admin_password}}\"\n}",
        'auth/forgot-password' => "{\n  \"email\": \"{{user_email}}\"\n}",
        'auth/forgot-password/verify-otp' => "{\n  \"email\": \"{{user_email}}\",\n  \"otp\": \"123456\"\n}",
        'auth/reset-password' => "{\n  \"email\": \"{{user_email}}\",\n  \"token\": \"reset-token\",\n  \"password\": \"newpassword123\",\n  \"password_confirmation\": \"newpassword123\"\n}",
        'auth/phone/request-otp' => "{\n  \"phone\": \"+2348012345678\"\n}",
        'auth/phone/verify-otp' => "{\n  \"phone\": \"+2348012345678\",\n  \"otp\": \"123456\"\n}",
        'auth/two-factor/verify' => "{\n  \"login_token\": \"\",\n  \"otp\": \"123456\"\n}",
        'auth/social/{provider}/login' => "{\n  \"access_token\": \"google-id-token\"\n}",
        'admin/users' => "{\n  \"role\": \"user\",\n  \"per_page\": 10\n}",
        'admin/users/summary' => "{\n  \"role\": \"user\"\n}",
        'admin/users/view' => "{\n  \"user_id\": {{id}}\n}",
        'admin/users/status-change' => "{\n  \"user_id\": {{id}},\n  \"status\": \"active\"\n}",
        'admin/users/delete' => "{\n  \"user_id\": {{id}}\n}",
        'admin/categories' => "{\n  \"per_page\": 20\n}",
        'admin/categories/create' => "{\n  \"name\": \"Restaurants & Food\",\n  \"subcategories\": \"Fast Food, Cafes\"\n}",
        'admin/categories/view' => "{\n  \"id\": {{id}}\n}",
        'admin/categories/update' => "{\n  \"id\": {{id}},\n  \"name\": \"Updated Category\"\n}",
        'admin/categories/delete' => "{\n  \"id\": {{id}}\n}",
        'admin/locations' => "{\n  \"per_page\": 50\n}",
        'admin/locations/store' => "{\n  \"country\": \"Nigeria\",\n  \"state\": \"Lagos\",\n  \"lga\": \"Ikeja\",\n  \"boost_enabled\": true\n}",
        'admin/locations/update' => "{\n  \"id\": {{id}},\n  \"boost_count\": 5\n}",
        'admin/locations/delete' => "{\n  \"id\": {{id}}\n}",
        'admin/locations/boost-active' => "{\n  \"id\": {{id}},\n  \"is_active\": true\n}",
        'admin/locations/status-change' => "{\n  \"id\": {{id}},\n  \"status\": \"active\"\n}",
        'admin/locations/vendors' => "{\n  \"lga_id\": {{id}}\n}",
        'admin/locations/vendors/sync' => "{\n  \"lga_id\": {{id}},\n  \"vendor_ids\": [1, 2]\n}",
        'admin/business-info' => "{\n  \"per_page\": 10,\n  \"status\": \"pending\"\n}",
        'admin/business-info/view' => "{\n  \"id\": {{business_id}}\n}",
        'admin/business-info/status-change' => "{\n  \"id\": {{business_id}},\n  \"status\": \"approved\"\n}",
        'admin/reviews' => "{\n  \"per_page\": 10\n}",
        'admin/reviews/{review}/update' => "{\n  \"status\": \"approved\"\n}",
        'admin/boost-requests' => "{\n  \"per_page\": 10\n}",
        'admin/boost-requests/show' => "{\n  \"id\": {{id}}\n}",
        'admin/boost-requests/approve' => "{\n  \"id\": {{id}}\n}",
        'admin/boost-requests/reject' => "{\n  \"id\": {{id}},\n  \"reason\": \"Rejected\"\n}",
        'contact-messages' => "{\n  \"name\": \"John\",\n  \"email\": \"john@example.com\",\n  \"subject\": \"Hello\",\n  \"message\": \"Test message\"\n}",
        'reviews' => "{\n  \"business_id\": {{business_id}},\n  \"per_page\": 10\n}",
        'reviews/store' => "{\n  \"business_id\": {{business_id}},\n  \"rating\": 5,\n  \"comment\": \"Great service!\"\n}",
        'user/favorites/toggle' => "{\n  \"business_id\": {{business_id}}\n}",
        'user/follows/toggle' => "{\n  \"user_id\": {{id}}\n}",
        'user/settings' => "{\n  \"notification_email\": true,\n  \"notification_push\": true\n}",
        'user/profile' => "{\n  \"name\": \"John Doe\"\n}",
        'user/wallet/top-up' => "{\n  \"amount\": 5000\n}",
        'user/wallet/top-up/confirm' => "{\n  \"reference\": \"paystack-ref\"\n}",
        'conversations' => "{\n  \"recipient_id\": {{id}}\n}",
        'conversations/{conversation}/messages' => "{\n  \"body\": \"Hello!\"\n}",
        'conversations/{conversation}/typing' => "{\n  \"is_typing\": true\n}",
        'messages/{message}/read' => '{}',
        'notifications/read-bulk' => "{\n  \"ids\": [1, 2, 3]\n}",
        'vendor/business/create' => "{\n  \"business_name\": \"My Shop\",\n  \"category_id\": 1,\n  \"description\": \"Business description\"\n}",
        'vendor/business/update' => "{\n  \"business_name\": \"Updated Shop Name\"\n}",
        'vendor/boost/request' => "{\n  \"lga_id\": {{id}},\n  \"duration_days\": 7\n}",
        'vendor/boost/payment/init' => "{\n  \"lga_id\": {{id}},\n  \"duration_days\": 7\n}",
        'vendor/boost/payment/confirm' => "{\n  \"reference\": \"paystack-ref\"\n}",
        'vendor/subscription/payment/init' => "{\n  \"package_id\": 1\n}",
        'vendor/subscription/payment/confirm' => "{\n  \"reference\": \"paystack-ref\"\n}",
        'vendor/verification/apply' => "{\n  \"package_id\": 1\n}",
        'vendor/reviews/{review}/reply' => "{\n  \"body\": \"Thank you for your review!\"\n}",
        'vendor/catalog' => "{\n  \"name\": \"Product Name\",\n  \"price\": 1000,\n  \"description\": \"Item description\"\n}",
        'vendor/catalog/{catalogItem}' => "{\n  \"name\": \"Updated Product\"\n}",
        'admin/messaging/conversations/{conversation}/messages' => "{\n  \"body\": \"Admin message\"\n}",
        'admin/cms/upsert' => "{\n  \"slug\": \"about\",\n  \"title\": \"About Us\",\n  \"content\": \"<p>Content here</p>\"\n}",
        'admin/verifications/approve' => "{\n  \"id\": {{id}}\n}",
        'admin/admins' => "{\n  \"name\": \"New Admin\",\n  \"email\": \"admin@example.com\",\n  \"password\": \"password123\",\n  \"role\": \"admin\"\n}",
        'admin/roles' => "{\n  \"name\": \"editor\",\n  \"permissions\": []\n}",
        'realtime/test-broadcast' => "{\n  \"channel\": \"test\",\n  \"event\": \"TestEvent\",\n  \"payload\": {\"message\": \"hello\"}\n}",
    ];
}
