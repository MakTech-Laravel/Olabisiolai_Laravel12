<?php

/**
 * Regenerates the Postman collection from the current route table + FormRequest validation rules.
 *
 * Walks every api/v1/* route, resolves the FormRequest (if any) type-hinted on the controller
 * method, reads its rules() to build a realistic example request body, and groups everything
 * into the same module folders as the previous hand-maintained collection.
 *
 * Usage: php scripts/generate-postman-collection.php [output-path]
 */

require __DIR__.'/lib/route-introspection.php';

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

if (getenv('GENERATOR_DEBUG_FIELD')) {
    [$dbgController, $dbgMethod] = explode('@', getenv('GENERATOR_DEBUG_FIELD'));
    $dbg = extractInlineValidateRules($dbgController, $dbgMethod);
    fwrite(STDOUT, "extractInlineValidateRules({$dbgController}, {$dbgMethod}):\n");
    var_export($dbg);
    fwrite(STDOUT, "\n");
    exit(0);
}

$outputPath = $argv[1] ?? __DIR__.'/../../Olabisiolai Local.postman_collection.json';

// ---------------------------------------------------------------------------
// Example value generation
// ---------------------------------------------------------------------------

function idExampleFor(string $field): int
{
    return match (true) {
        str_contains($field, 'category') => 3,
        str_contains($field, 'location') => 12,
        str_contains($field, 'business') => 1,
        str_contains($field, 'review') => 5,
        str_contains($field, 'conversation') => 1,
        str_contains($field, 'message') => 10,
        str_contains($field, 'payment') => 1,
        str_contains($field, 'user') => 2,
        default => 1,
    };
}

function nameExampleFor(string $field): string
{
    return match (true) {
        $field === 'first_name' => 'John',
        $field === 'last_name' => 'Doe',
        str_contains($field, 'business_name') => 'Adaeze Catering Services',
        str_contains($field, 'cardholder') => 'John Doe',
        str_contains($field, 'name') => 'John Doe',
        default => 'Example Name',
    };
}

/**
 * @param  array<int, string>  $tokens
 */
function exampleScalarFor(string $field, array $tokens): mixed
{
    $fieldLower = Str::lower($field);

    foreach ($tokens as $token) {
        if (str_starts_with($token, 'in:')) {
            $values = parseInList($token);
            if ($values) {
                $first = $values[0];

                return is_numeric($first) ? $first + 0 : $first;
            }
        }
    }

    if (in_array('boolean', $tokens, true)) {
        return true;
    }

    if (in_array('confirmed', $tokens, true) || $fieldLower === 'password' || $fieldLower === 'password_confirmation') {
        return 'Password@123';
    }

    foreach ($tokens as $token) {
        if (str_starts_with($token, 'class:App\\Rules\\NigerianPhoneNumber')) {
            return '+2348012345678';
        }
    }

    if (in_array('email', $tokens, true)) {
        return 'john.doe@example.com';
    }

    if (in_array('url', $tokens, true)) {
        return 'https://example.com';
    }

    if (in_array('date', $tokens, true) || in_array('date_format:Y-m-d', $tokens, true)) {
        return '2026-01-15';
    }

    foreach ($tokens as $token) {
        if (str_starts_with($token, 'date_format:') && str_contains($token, 'H:i')) {
            return '2026-01-15T10:30:00Z';
        }
    }

    if (in_array('integer', $tokens, true)) {
        if (str_ends_with($fieldLower, '_id') || $fieldLower === 'id') {
            return idExampleFor($fieldLower);
        }
        if (str_contains($fieldLower, 'duration') || str_contains($fieldLower, 'days')) {
            return 7;
        }
        if (str_contains($fieldLower, 'per_page')) {
            return 15;
        }
        if (str_contains($fieldLower, 'page')) {
            return 1;
        }
        if (str_contains($fieldLower, 'rating')) {
            return 5;
        }

        return 10;
    }

    if (in_array('numeric', $tokens, true)) {
        if (str_contains($fieldLower, 'amount') || str_contains($fieldLower, 'budget') || str_contains($fieldLower, 'price')) {
            return 5000;
        }

        return 99.99;
    }

    foreach ($tokens as $token) {
        if (str_starts_with($token, 'exists:')) {
            return idExampleFor($fieldLower);
        }
    }

    if (str_ends_with($fieldLower, '_id') || $fieldLower === 'id') {
        return idExampleFor($fieldLower);
    }

    if (str_contains($fieldLower, 'uuid')) {
        return '550e8400-e29b-41d4-a716-446655440000';
    }

    if (str_contains($fieldLower, 'phone')) {
        return '+2348012345678';
    }

    if (str_contains($fieldLower, 'name')) {
        return nameExampleFor($fieldLower);
    }

    if (str_contains($fieldLower, 'email')) {
        return 'john.doe@example.com';
    }

    if (str_contains($fieldLower, 'whatsapp')) {
        return '+2348012345678';
    }

    if (str_contains($fieldLower, 'website') || $fieldLower === 'url') {
        return 'https://example.com';
    }

    if ($fieldLower === 'ref' || str_contains($fieldLower, 'referral_code') || str_contains($fieldLower, 'code')) {
        return 'GID1A2B3C';
    }

    if (str_contains($fieldLower, 'token') || str_contains($fieldLower, 'reference') || $fieldLower === 'tx_ref') {
        return 'ref_'.substr(md5($fieldLower), 0, 12);
    }

    if (str_contains($fieldLower, 'address')) {
        return '12 Adeola Odeku Street, Victoria Island';
    }

    if (str_contains($fieldLower, 'description') || str_contains($fieldLower, 'note') || str_contains($fieldLower, 'message') || str_contains($fieldLower, 'body') || str_contains($fieldLower, 'reason')) {
        return 'Example description text.';
    }

    if (str_contains($fieldLower, 'title')) {
        return 'Example Title';
    }

    if (str_contains($fieldLower, 'status')) {
        return 'active';
    }

    if (in_array('string', $tokens, true)) {
        return 'Example Text';
    }

    return 'Example Text';
}

/**
 * Build a nested example body from a flat Laravel rules() array (dot notation supported).
 *
 * @param  array<string, array<int, mixed>>  $rules
 * @return array<string, mixed>
 */
function buildExampleBody(array $rules): array
{
    $tokensByField = [];
    foreach ($rules as $field => $ruleset) {
        $tokensByField[$field] = ruleTokens((array) $ruleset);
    }

    $topLevel = [];
    foreach (array_keys($tokensByField) as $field) {
        if (! str_contains($field, '.')) {
            $topLevel[] = $field;
        }
    }

    $body = [];
    foreach ($topLevel as $field) {
        $tokens = $tokensByField[$field];

        $isFile = in_array('file', $tokens, true) || in_array('image', $tokens, true);
        if ($isFile) {
            continue; // handled as multipart, not JSON body
        }

        if (in_array('array', $tokens, true)) {
            $itemField = $field.'.*';
            $itemTokens = $tokensByField[$itemField] ?? null;

            if ($itemTokens !== null) {
                // Array of scalars, e.g. "services_offered.*" => ["required","string"]
                $nestedObjectPrefix = $field.'.*.';
                $nestedKeys = array_filter(
                    array_keys($tokensByField),
                    fn (string $k) => str_starts_with($k, $nestedObjectPrefix),
                );

                if ($nestedKeys) {
                    $item = [];
                    foreach ($nestedKeys as $nestedKey) {
                        $sub = substr($nestedKey, strlen($nestedObjectPrefix));
                        if (str_contains($sub, '.')) {
                            continue; // skip >2 levels deep for pragmatism
                        }
                        $subTokens = $tokensByField[$nestedKey];
                        if (in_array('file', $subTokens, true) || in_array('image', $subTokens, true)) {
                            continue;
                        }
                        $item[$sub] = exampleScalarFor($sub, $subTokens);
                    }
                    $body[$field] = $item === [] ? [] : [$item];
                } else {
                    $body[$field] = [exampleScalarFor($field, $itemTokens), exampleScalarFor($field, $itemTokens)];
                }
            } else {
                $body[$field] = [];
            }

            continue;
        }

        $body[$field] = exampleScalarFor($field, $tokens);

        if (in_array('confirmed', $tokens, true)) {
            $body[$field.'_confirmation'] = $body[$field];
        }
    }

    return $body;
}

// ---------------------------------------------------------------------------
// FormRequest resolution (rule extraction lives in lib/route-introspection.php)
// ---------------------------------------------------------------------------

/**
 * @return array{0: array<string, mixed>, 1: bool} [example body, isMultipart]
 */
function resolveRequestExample(string $controller, string $method): array
{
    [$rules, $isMultipart] = resolveRulesForAction($controller, $method);

    return [buildExampleBody($rules), $isMultipart];
}

// ---------------------------------------------------------------------------
// Route classification
// ---------------------------------------------------------------------------

/** @return array{folder: string, subfolder: string} */
function classifyRoute(string $uriAfterV1): array
{
    $segments = explode('/', trim($uriAfterV1, '/'));
    $first = $segments[0] ?? '';
    $second = $segments[1] ?? '';

    if ($first === 'admin') {
        if ($second === 'messaging') {
            return ['folder' => '6. Messaging', 'subfolder' => 'Admin Messaging'];
        }

        return ['folder' => '5. Admin', 'subfolder' => Str::headline($second ?: 'General')];
    }

    if ($first === 'vendor') {
        return ['folder' => '4. Vendor', 'subfolder' => Str::headline($second ?: 'General')];
    }

    if ($first === 'user') {
        return ['folder' => '3. User', 'subfolder' => Str::headline($second ?: 'General')];
    }

    if ($first === 'auth') {
        if ($second === 'admin') {
            return ['folder' => '2. Auth', 'subfolder' => 'Admin Auth'];
        }
        if ($second === 'social') {
            return ['folder' => '2. Auth', 'subfolder' => 'Social'];
        }
        if (in_array($second, ['two-factor', 'device'], true)) {
            return ['folder' => '2. Auth', 'subfolder' => '2FA & Device'];
        }
        if (in_array($second, ['forgot-password', 'reset-password'], true)) {
            return ['folder' => '2. Auth', 'subfolder' => 'Password Reset'];
        }
        if ($second === 'phone') {
            return ['folder' => '2. Auth', 'subfolder' => 'Phone Login'];
        }
        if ($second === 'register') {
            return ['folder' => '2. Auth', 'subfolder' => 'Registration'];
        }

        return ['folder' => '2. Auth', 'subfolder' => 'User Auth'];
    }

    if (in_array($first, ['conversations', 'messages', 'attachments', 'presence'], true)) {
        return ['folder' => '6. Messaging', 'subfolder' => Str::headline($first)];
    }

    if (in_array($first, ['notifications', 'realtime'], true)) {
        return ['folder' => '7. Notifications & Realtime', 'subfolder' => 'General'];
    }

    return ['folder' => '1. Public', 'subfolder' => Str::headline($first ?: 'General')];
}

function authBlockFor(array $middleware): ?array
{
    return match (authKindFor($middleware)) {
        'admin' => ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{admin_token}}', 'type' => 'string']]],
        'user' => ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{user_token}}', 'type' => 'string']]],
        default => null,
    };
}

function postmanPathVariable(string $paramName): string
{
    return '{{'.pathVariableFor($paramName).'}}';
}

function friendlyRequestName(string $method, string $uriAfterV1): string
{
    $segments = explode('/', trim($uriAfterV1, '/'));
    $last = end($segments);
    $last = preg_replace('/^\{.*\}$/', '', $last) ?: ($segments[count($segments) - 2] ?? 'Index');
    $label = Str::headline(str_replace(['-', '_'], ' ', $last));

    return strtoupper($method).' - '.($label ?: 'Index');
}

// ---------------------------------------------------------------------------
// Build collection
// ---------------------------------------------------------------------------

$folders = []; // folder => subfolder => [items]

/** @var Route $route */
foreach (RouteFacade::getRoutes() as $route) {
    $uri = $route->uri();
    if (! str_starts_with($uri, 'api/v1/')) {
        continue;
    }

    $methods = array_values(array_diff($route->methods(), ['HEAD']));
    if ($methods === []) {
        continue;
    }

    $action = $route->getActionName();
    if ($action === 'Closure') {
        $controller = '';
        $ctrlMethod = '';
    } else {
        [$controller, $ctrlMethod] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];
    }

    $uriAfterV1 = Str::after($uri, 'api/v1/');
    ['folder' => $folder, 'subfolder' => $subfolder] = classifyRoute($uriAfterV1);
    $middleware = $route->gatherMiddleware();
    $auth = authBlockFor($middleware);

    // Convert {param} segments to collection-variable placeholders.
    $pathSegments = array_map(
        function (string $segment): string {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $paramName = trim($segment, '{}?');

                return postmanPathVariable($paramName);
            }

            return $segment;
        },
        explode('/', $uri),
    );
    $pathForPostman = implode('/', $pathSegments);

    foreach ($methods as $method) {
        [$exampleBody, $isMultipart] = in_array($method, ['POST', 'PUT', 'PATCH'], true)
            ? resolveRequestExample($controller, $ctrlMethod)
            : [[], false];

        $item = [
            'name' => friendlyRequestName($method, $uriAfterV1),
            'request' => [
                'method' => $method,
                'header' => array_values(array_filter([
                    ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
                    $isMultipart || $exampleBody === [] ? null : ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
                ])),
                'url' => [
                    'raw' => '{{base_url}}/'.Str::after($pathForPostman, 'api/v1/'),
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', Str::after($pathForPostman, 'api/v1/')),
                ],
                'description' => sprintf(
                    "**Route name:** `%s`\n\n**Controller:** `%s@%s`\n\n**Path:** `/%s`",
                    $route->getName() ?? '',
                    $controller,
                    $ctrlMethod,
                    Str::after($uri, 'api/v1'),
                ),
            ],
            'response' => [],
        ];

        if ($auth !== null) {
            $item['request']['auth'] = $auth;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            if ($isMultipart) {
                $formdata = [];
                foreach ($exampleBody as $key => $value) {
                    if (is_array($value)) {
                        continue; // nested array fields under multipart need manual index keys, skip in the generic example
                    }
                    $formdata[] = ['key' => $key, 'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'type' => 'text'];
                }
                $formdata[] = ['key' => 'document', 'type' => 'file', 'src' => []];
                $item['request']['body'] = ['mode' => 'formdata', 'formdata' => $formdata];
            } else {
                $item['request']['body'] = [
                    'mode' => 'raw',
                    'raw' => json_encode($exampleBody === [] ? new stdClass : $exampleBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'options' => ['raw' => ['language' => 'json']],
                ];
            }
        }

        $folders[$folder][$subfolder][] = $item;
    }
}

ksort($folders);

$collectionItems = [];
foreach ($folders as $folderName => $subfolders) {
    ksort($subfolders);
    $subItems = [];
    foreach ($subfolders as $subName => $requests) {
        $subItems[] = ['name' => $subName, 'item' => $requests];
    }
    $collectionItems[] = ['name' => $folderName, 'item' => $subItems];
}

// Hand-maintained "Quick Start" folder with token-capture test scripts, kept first.
$quickStart = [
    'name' => '0. Quick Start',
    'item' => [
        [
            'name' => 'Admin Auth',
            'item' => [
                [
                    'name' => 'Admin Login',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
                            ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
                        ],
                        'url' => ['raw' => '{{base_url}}/admin/login', 'host' => ['{{base_url}}'], 'path' => ['admin', 'login']],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode(['email' => '{{admin_email}}', 'password' => '{{admin_password}}'], JSON_PRETTY_PRINT),
                            'options' => ['raw' => ['language' => 'json']],
                        ],
                    ],
                    'event' => [[
                        'listen' => 'test',
                        'script' => ['type' => 'text/javascript', 'exec' => [
                            'if (pm.response.code === 200 || pm.response.code === 201) {',
                            '    const json = pm.response.json();',
                            '    const token = json.token || json.data?.token || json.access_token;',
                            '    if (token) {',
                            "        pm.collectionVariables.set('admin_token', token);",
                            '    }',
                            '}',
                        ]],
                    ]],
                    'response' => [],
                ],
            ],
        ],
        [
            'name' => 'User Auth',
            'item' => [
                [
                    'name' => 'User Login',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Accept', 'value' => 'application/json', 'type' => 'text'],
                            ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'],
                        ],
                        'url' => ['raw' => '{{base_url}}/auth/login', 'host' => ['{{base_url}}'], 'path' => ['auth', 'login']],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode(['email' => '{{user_email}}', 'password' => '{{user_password}}', 'role' => 'user'], JSON_PRETTY_PRINT),
                            'options' => ['raw' => ['language' => 'json']],
                        ],
                    ],
                    'event' => [[
                        'listen' => 'test',
                        'script' => ['type' => 'text/javascript', 'exec' => [
                            'if (pm.response.code === 200 || pm.response.code === 201) {',
                            '    const json = pm.response.json();',
                            '    const token = json.token || json.data?.token || json.access_token;',
                            '    if (token) {',
                            "        pm.collectionVariables.set('user_token', token);",
                            '    }',
                            '}',
                        ]],
                    ]],
                    'response' => [],
                ],
            ],
        ],
    ],
];
array_unshift($collectionItems, $quickStart);

$collection = [
    'info' => [
        '_postman_id' => '094a96c6-e4a7-42ac-82a4-b9054b026a04',
        'name' => 'Olabisiolai Local',
        'description' => "Olabisiolai / Gidira API (Laravel 12) - local development collection.\n\n## Setup\n1. Set `base_url` (default: `http://localhost:8000/api/v1`).\n2. Run **User Login** or **Admin Login** under `0. Quick Start` - tokens auto-save to `user_token` / `admin_token`.\n3. Authenticated requests use Bearer token automatically.\n\nRegenerated from the live route table + FormRequest validation rules via `scripts/generate-postman-collection.php`. Re-run after route changes.",
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
        ['key' => 'category_id', 'value' => '1', 'type' => 'string'],
        ['key' => 'id', 'value' => '1', 'type' => 'string'],
    ],
    'item' => $collectionItems,
];

file_put_contents($outputPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$routeCount = 0;
foreach ($folders as $subfolders) {
    foreach ($subfolders as $requests) {
        $routeCount += count($requests);
    }
}

fwrite(STDOUT, "Generated collection with {$routeCount} routes (+ Quick Start) at: {$outputPath}\n");
