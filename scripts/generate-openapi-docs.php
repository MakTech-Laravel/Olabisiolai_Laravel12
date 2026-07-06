<?php

/**
 * Generates OpenAPI (swagger-php) PHP-attribute path definitions for every api/v1/* route
 * that doesn't already have a hand-written #[OA\Get/Post/Put/Patch/Delete] annotation
 * somewhere in app/Http/Controllers.
 *
 * Writes one file per module/sub-module under app/OpenApi/Paths/, each a small stub class
 * whose (never-called) private methods each carry one OA path-operation attribute — the
 * same "documentation lives beside the code, but not inside it" pattern already used by
 * app/OpenApi/BaseInfo.php and app/OpenApi/Schemas/*.
 *
 * Usage: php scripts/generate-openapi-docs.php
 * Then:  php artisan l5-swagger:generate
 */

require __DIR__.'/lib/route-introspection.php';

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// PHP literal serializer — renders arbitrary values as valid PHP source, and a
// thin `new OA\X(...)` named-argument constructor renderer on top of it.
// ---------------------------------------------------------------------------

function phpLit(mixed $value, int $indent = 0): string
{
    if (is_string($value)) {
        return var_export($value, true);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if ($value === null) {
        return 'null';
    }
    if ($value instanceof RawPhp) {
        return $value->code;
    }
    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }
        $pad = str_repeat('    ', $indent);
        $padIn = str_repeat('    ', $indent + 1);
        $isList = array_is_list($value);
        $lines = [];
        foreach ($value as $k => $v) {
            $valStr = phpLit($v, $indent + 1);
            $lines[] = $isList ? "{$padIn}{$valStr}" : "{$padIn}".var_export((string) $k, true)." => {$valStr}";
        }

        return "[\n".implode(",\n", $lines).",\n{$pad}]";
    }

    throw new RuntimeException('Unsupported literal type: '.get_debug_type($value));
}

/** Marker for a value that should be emitted as raw PHP source (e.g. `new OA\Property(...)`). */
final class RawPhp
{
    public function __construct(public string $code) {}
}

/**
 * Render `new {$class}(arg: value, ...)`, dropping null values so the output stays terse.
 *
 * @param  array<string, mixed>  $args
 */
function renderArgList(array $args, int $indent): string
{
    $args = array_filter($args, fn ($v) => $v !== null);
    if ($args === []) {
        return '()';
    }

    $pad = str_repeat('    ', $indent);
    $padIn = str_repeat('    ', $indent + 1);
    $lines = [];
    foreach ($args as $key => $value) {
        $lines[] = "{$padIn}{$key}: ".phpLit($value, $indent + 1);
    }

    return "(\n".implode(",\n", $lines).",\n{$pad})";
}

/**
 * @param  array<string, mixed>  $args
 */
function oaNew(string $class, array $args, int $indent = 0): RawPhp
{
    return new RawPhp("new {$class}".renderArgList($args, $indent));
}

/**
 * Same as oaNew() but without the `new` keyword — for the outermost attribute itself,
 * which uses `#[OA\Post(...)]` syntax, not `#[new OA\Post(...)]`.
 *
 * @param  array<string, mixed>  $args
 */
function oaAttr(string $class, array $args, int $indent = 0): RawPhp
{
    return new RawPhp("{$class}".renderArgList($args, $indent));
}

// ---------------------------------------------------------------------------
// Detect already-documented (METHOD, path) pairs so we never duplicate an
// existing hand-written annotation (swagger-php errors on duplicate paths).
// ---------------------------------------------------------------------------

/**
 * @return array<string, true> keyed by "METHOD /v1/path"
 */
function existingOaOperations(string $controllersDir): array
{
    $covered = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersDir));
    foreach ($rii as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $src = file_get_contents($file->getPathname());
        if (preg_match_all('/#\[OA\\\\(Get|Post|Put|Patch|Delete)\(\s*path:\s*[\'"]([^\'"]+)[\'"]/', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $covered[strtoupper($mm[1]).' '.$mm[2]] = true;
            }
        }
    }

    return $covered;
}

// ---------------------------------------------------------------------------
// Field -> OpenAPI schema property, derived from Laravel validation rule tokens
// ---------------------------------------------------------------------------

function oaFieldSchema(string $field, array $tokens): array
{
    $fieldLower = Str::lower($field);
    $spec = ['property' => $field];

    foreach ($tokens as $token) {
        if (str_starts_with($token, 'in:')) {
            $values = parseInList($token);
            if ($values) {
                $numeric = array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $values);
                $spec['enum'] = $numeric;
                $spec['example'] = $numeric[0];
            }
        }
    }

    if (in_array('boolean', $tokens, true)) {
        $spec['type'] = 'boolean';
        $spec['example'] ??= true;
    } elseif (in_array('integer', $tokens, true)) {
        $spec['type'] = 'integer';
        $spec['example'] ??= str_ends_with($fieldLower, '_id') || $fieldLower === 'id' ? 1 : 10;
    } elseif (in_array('numeric', $tokens, true)) {
        $spec['type'] = 'number';
        $spec['example'] ??= str_contains($fieldLower, 'amount') || str_contains($fieldLower, 'budget') || str_contains($fieldLower, 'price') ? 5000 : 99.99;
    } elseif (in_array('email', $tokens, true)) {
        $spec['type'] = 'string';
        $spec['format'] = 'email';
        $spec['example'] ??= 'john.doe@example.com';
    } elseif (in_array('url', $tokens, true)) {
        $spec['type'] = 'string';
        $spec['format'] = 'uri';
        $spec['example'] ??= 'https://example.com';
    } elseif (in_array('date', $tokens, true)) {
        $spec['type'] = 'string';
        $spec['format'] = 'date';
        $spec['example'] ??= '2026-01-15';
    } elseif (str_contains($fieldLower, 'uuid')) {
        $spec['type'] = 'string';
        $spec['format'] = 'uuid';
        $spec['example'] ??= '550e8400-e29b-41d4-a716-446655440000';
    } else {
        $spec['type'] = 'string';
        if ($fieldLower === 'password' || $fieldLower === 'password_confirmation') {
            $spec['format'] = 'password';
            $spec['example'] ??= 'Password@123';
        } elseif (str_contains($fieldLower, 'phone') || str_contains($fieldLower, 'whatsapp')) {
            $spec['example'] ??= '+2348012345678';
        } elseif (str_contains($fieldLower, 'name')) {
            $spec['example'] ??= $fieldLower === 'first_name' ? 'John' : ($fieldLower === 'last_name' ? 'Doe' : 'John Doe');
        } elseif (str_contains($fieldLower, 'description') || str_contains($fieldLower, 'note') || str_contains($fieldLower, 'message') || str_contains($fieldLower, 'body') || str_contains($fieldLower, 'reason')) {
            $spec['example'] ??= 'Example description text.';
        } elseif (str_contains($fieldLower, 'title')) {
            $spec['example'] ??= 'Example Title';
        } elseif (str_contains($fieldLower, 'address')) {
            $spec['example'] ??= '12 Adeola Odeku Street, Victoria Island';
        } elseif ($fieldLower === 'ref' || str_contains($fieldLower, 'code')) {
            $spec['example'] ??= 'GID1A2B3C';
        } else {
            $spec['example'] ??= 'Example Text';
        }
    }

    if (in_array('nullable', $tokens, true)) {
        $spec['nullable'] = true;
    }

    foreach ($tokens as $token) {
        if (preg_match('/^max:(\d+)$/', $token, $m) && $spec['type'] === 'string') {
            $spec['maxLength'] = (int) $m[1];
        }
        if (preg_match('/^min:(\d+)$/', $token, $m) && $spec['type'] === 'string') {
            $spec['minLength'] = (int) $m[1];
        }
    }

    return $spec;
}

/**
 * Build OA\Property specs (as arrays of ctor args) for a flat rules() array, dot-notation
 * arrays included. Returns [properties, requiredFieldNames, hasFileUpload].
 *
 * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: bool}
 */
function oaPropertiesFromRules(array $rules): array
{
    $tokensByField = [];
    foreach ($rules as $field => $ruleset) {
        $tokensByField[$field] = ruleTokens((array) $ruleset);
    }

    $topLevel = array_values(array_filter(array_keys($tokensByField), fn (string $f) => ! str_contains($f, '.')));

    $properties = [];
    $required = [];
    $hasFile = false;

    foreach ($topLevel as $field) {
        $tokens = $tokensByField[$field];

        if (in_array('file', $tokens, true) || in_array('image', $tokens, true)) {
            $hasFile = true;
            $properties[] = ['property' => $field, 'type' => 'string', 'format' => 'binary'];
            if (in_array('required', $tokens, true)) {
                $required[] = $field;
            }

            continue;
        }

        if (in_array('array', $tokens, true)) {
            $nestedPrefix = $field.'.*.';
            $nestedKeys = array_values(array_filter(array_keys($tokensByField), fn (string $k) => str_starts_with($k, $nestedPrefix) && ! str_contains(substr($k, strlen($nestedPrefix)), '.')));

            if ($nestedKeys) {
                $itemProps = [];
                foreach ($nestedKeys as $nestedKey) {
                    $sub = substr($nestedKey, strlen($nestedPrefix));
                    $subTokens = $tokensByField[$nestedKey];
                    if (in_array('file', $subTokens, true) || in_array('image', $subTokens, true)) {
                        $hasFile = true;
                        $itemProps[] = ['property' => $sub, 'type' => 'string', 'format' => 'binary'];

                        continue;
                    }
                    $itemProps[] = oaFieldSchema($sub, $subTokens);
                }
                $properties[] = ['property' => $field, 'type' => 'array', 'items' => ['type' => 'object', 'properties' => $itemProps]];
            } else {
                $itemTokens = $tokensByField[$field.'.*'] ?? ['string'];
                $itemSchema = oaFieldSchema($field, $itemTokens);
                unset($itemSchema['property']);
                $properties[] = ['property' => $field, 'type' => 'array', 'items' => $itemSchema];
            }
        } else {
            $properties[] = oaFieldSchema($field, $tokens);

            if (in_array('confirmed', $tokens, true)) {
                $properties[] = ['property' => $field.'_confirmation', 'type' => 'string', 'format' => 'password', 'example' => 'Password@123'];
            }
        }

        if (in_array('required', $tokens, true)) {
            $required[] = $field;
        }
    }

    return [$properties, $required, $hasFile];
}

/**
 * Recursively render a property spec array (as produced by oaFieldSchema/oaPropertiesFromRules)
 * into a `new OA\Property(...)` RawPhp node.
 */
function renderProperty(array $spec, int $indent): RawPhp
{
    $args = $spec;
    if (isset($args['items']) && is_array($args['items'])) {
        $items = $args['items'];
        if (isset($items['properties'])) {
            $args['items'] = oaNew('OA\\Items', [
                'type' => $items['type'] ?? 'object',
                'properties' => new RawPhp(renderPropertyList($items['properties'], $indent + 2)),
            ], $indent + 1);
        } else {
            $args['items'] = oaNew('OA\\Items', [
                'type' => $items['type'] ?? 'string',
                'format' => $items['format'] ?? null,
                'example' => $items['example'] ?? null,
            ], $indent + 1);
        }
    }
    if (isset($args['enum'])) {
        $args['enum'] = $args['enum']; // plain array, fine as-is for phpLit
    }

    return oaNew('OA\\Property', $args, $indent);
}

function renderPropertyList(array $specs, int $indent): string
{
    $pad = str_repeat('    ', $indent);
    $padIn = str_repeat('    ', $indent + 1);
    $lines = array_map(fn (array $spec) => $padIn.renderProperty($spec, $indent + 1)->code, $specs);

    return "[\n".implode(",\n", $lines).",\n{$pad}]";
}

// ---------------------------------------------------------------------------
// Route classification (tags / grouping / summaries)
// ---------------------------------------------------------------------------

/** @return array{0: list<string>, 1: string} [tags, groupFileKey] */
function classifyForOpenApi(string $uriAfterV1): array
{
    $segments = explode('/', trim($uriAfterV1, '/'));
    $first = $segments[0] ?? '';
    $second = $segments[1] ?? '';

    $billingSecondSegments = ['boost', 'verification', 'subscription', 'payments', 'payment-methods', 'pricing'];

    if ($first === 'admin') {
        if ($second === 'messaging') {
            return [['Admin', 'Messaging'], 'AdminMessaging'];
        }
        $tags = in_array($second, $billingSecondSegments, true) ? ['Admin', 'Billing'] : ['Admin'];
        if (in_array($second, ['login', 'logout', 'two-factor'], true)) {
            $tags = ['Admin', 'Auth'];
        }

        return [$tags, 'Admin'.Str::studly($second ?: 'General')];
    }

    if ($first === 'vendor') {
        $tags = in_array($second, $billingSecondSegments, true) ? ['Vendors', 'Billing'] : ['Vendors'];

        return [$tags, 'Vendor'.Str::studly($second ?: 'General')];
    }

    if ($first === 'user') {
        $tags = in_array($second, $billingSecondSegments, true) ? ['Users', 'Billing'] : ['Users'];

        return [$tags, 'User'.Str::studly($second ?: 'General')];
    }

    if ($first === 'auth') {
        return [['Auth'], 'Auth'];
    }

    if (in_array($first, ['conversations', 'messages', 'attachments', 'presence'], true)) {
        return [['Messaging'], 'Messaging'];
    }

    if (in_array($first, ['notifications', 'realtime'], true)) {
        return [['Notifications'], 'Notifications'];
    }

    if (in_array($first, ['businesses', 'categories', 'locations'], true)) {
        return [['Businesses'], 'PublicBusinesses'];
    }

    if ($first === 'reviews' || $first === 'review-report-reasons') {
        return [['Reviews'], 'PublicReviews'];
    }

    return [['Public'], 'Public'.Str::studly($first ?: 'General')];
}

function humanizeSegment(string $segment): string
{
    return Str::headline(str_replace(['-', '_'], ' ', $segment));
}

function summaryFor(string $method, string $uriAfterV1, string $ctrlMethod): string
{
    $segments = array_values(array_filter(explode('/', trim($uriAfterV1, '/')), fn ($s) => ! str_starts_with($s, '{')));
    $resource = humanizeSegment(end($segments) ?: ($segments[0] ?? 'resource'));
    $verb = match (true) {
        in_array($ctrlMethod, ['index', 'all', 'list'], true) => 'List',
        in_array($ctrlMethod, ['store', 'create'], true) => 'Create',
        in_array($ctrlMethod, ['show', 'view'], true) => 'Get',
        in_array($ctrlMethod, ['update'], true) => 'Update',
        in_array($ctrlMethod, ['destroy', 'delete'], true) => 'Delete',
        default => match ($method) {
            'GET' => 'Get',
            'POST' => 'Submit',
            'PUT', 'PATCH' => 'Update',
            'DELETE' => 'Delete',
            default => 'Handle',
        },
    };

    return "{$verb} {$resource}";
}

function operationClassFor(string $method): string
{
    return match ($method) {
        'GET' => 'OA\\Get',
        'POST' => 'OA\\Post',
        'PUT' => 'OA\\Put',
        'PATCH' => 'OA\\Patch',
        'DELETE' => 'OA\\Delete',
        default => 'OA\\Get',
    };
}

// ---------------------------------------------------------------------------
// Build one operation attribute
// ---------------------------------------------------------------------------

function buildOperationAttribute(Route $route, string $method, string $controller, string $ctrlMethod): ?string
{
    $uri = $route->uri();
    $uriAfterV1 = Str::after($uri, 'api/v1/');
    $path = '/v1/'.$uriAfterV1;

    [$tags] = classifyForOpenApi($uriAfterV1);
    $middleware = $route->gatherMiddleware();
    $authKind = authKindFor($middleware);

    $parameters = [];
    foreach ($route->parameterNames() as $paramName) {
        $varName = pathVariableFor($paramName);
        $parameters[] = oaNew('OA\\Parameter', [
            'name' => $paramName,
            'in' => 'path',
            'required' => true,
            'schema' => oaNew('OA\\Schema', ['type' => $varName === 'id' || str_ends_with($varName, '_id') ? 'integer' : 'string'], 1),
            'example' => 1,
        ], 1);
    }

    [$rules, $isMultipart] = in_array($method, ['POST', 'PUT', 'PATCH'], true)
        ? resolveRulesForAction($controller, $ctrlMethod)
        : [[], false];

    [$properties, $required] = oaPropertiesFromRules($rules);

    $requestBody = null;
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $properties !== []) {
        $schemaArgs = [
            'required' => $required !== [] ? $required : null,
            'properties' => new RawPhp(renderPropertyList($properties, 3)),
        ];
        $content = $isMultipart
            ? oaNew('OA\\MediaType', ['mediaType' => 'multipart/form-data', 'schema' => oaNew('OA\\Schema', $schemaArgs, 3)], 2)
            : oaNew('OA\\JsonContent', $schemaArgs, 2);

        $requestBody = oaNew('OA\\RequestBody', [
            'required' => true,
            'content' => $content,
        ], 1);
    } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $rules === [] && ! in_array($ctrlMethod, ['logout', 'ping', 'offline'], true)) {
        // No detectable body — leave requestBody unset entirely (nothing to declare).
    }

    $isCreate = in_array($ctrlMethod, ['store', 'create', 'register'], true) && $method === 'POST';
    $successCode = $isCreate ? 201 : 200;

    $responses = [
        oaNew('OA\\Response', [
            'response' => $successCode,
            'description' => summaryFor($method, $uriAfterV1, $ctrlMethod).' successfully',
            'content' => oaNew('OA\\JsonContent', ['ref' => '#/components/schemas/ApiResponse'], 2),
        ], 1),
    ];

    if ($authKind !== null) {
        $responses[] = oaNew('OA\\Response', [
            'response' => 401,
            'description' => 'Unauthenticated',
            'content' => oaNew('OA\\JsonContent', ['ref' => '#/components/schemas/ErrorResponse'], 2),
        ], 1);
    }

    if ($route->parameterNames() !== []) {
        $responses[] = oaNew('OA\\Response', [
            'response' => 404,
            'description' => 'Not found',
            'content' => oaNew('OA\\JsonContent', ['ref' => '#/components/schemas/ErrorResponse'], 2),
        ], 1);
    }

    if ($properties !== []) {
        $responses[] = oaNew('OA\\Response', [
            'response' => 422,
            'description' => 'Validation error',
            'content' => oaNew('OA\\JsonContent', ['ref' => '#/components/schemas/ErrorResponse'], 2),
        ], 1);
    }

    $responses[] = oaNew('OA\\Response', [
        'response' => 500,
        'description' => 'Unexpected server error',
        'content' => oaNew('OA\\JsonContent', ['ref' => '#/components/schemas/ErrorResponse'], 2),
    ], 1);

    $attrArgs = [
        'path' => $path,
        'summary' => summaryFor($method, $uriAfterV1, $ctrlMethod),
        'tags' => $tags,
        'security' => $authKind !== null ? [['passport' => []]] : null,
        'parameters' => $parameters !== [] ? new RawPhp("[\n".implode(",\n", array_map(fn ($p) => str_repeat('    ', 2).$p->code, $parameters)).",\n".str_repeat('    ', 1).']') : null,
        'requestBody' => $requestBody,
        'responses' => new RawPhp("[\n".implode(",\n", array_map(fn ($r) => str_repeat('    ', 2).$r->code, $responses)).",\n".str_repeat('    ', 1).']'),
    ];

    $opClass = operationClassFor($method);
    $attr = oaAttr($opClass, $attrArgs, 0);

    return "#[{$attr->code}]";
}

// ---------------------------------------------------------------------------
// Walk routes, group into files, write
// ---------------------------------------------------------------------------

$existing = existingOaOperations(__DIR__.'/../app/Http/Controllers');

$groups = []; // groupFileKey => ['tags' => [...], 'operations' => [stubMethodName => attributeSource]]
$skipped = [];
$generated = 0;

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
    $uriAfterV1 = Str::after($uri, 'api/v1/');

    $action = $route->getActionName();
    if ($action === 'Closure') {
        $controller = '';
        $ctrlMethod = '';
    } else {
        [$controller, $ctrlMethod] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];
    }

    foreach ($methods as $method) {
        $path = '/v1/'.$uriAfterV1;

        if (isset($existing["{$method} {$path}"])) {
            continue;
        }

        $attributeSource = buildOperationAttribute($route, $method, $controller, $ctrlMethod);
        if ($attributeSource === null) {
            $skipped[] = "{$method} {$path}";

            continue;
        }

        [$tags, $groupKey] = classifyForOpenApi($uriAfterV1);

        $stubName = 'op'.preg_replace('/[^A-Za-z0-9]/', '', ucfirst(Str::camel($method.'_'.str_replace('/', '_', $uriAfterV1))));
        $stubName = substr($stubName, 0, 120).'_'.substr(md5($method.$path), 0, 6);

        $groups[$groupKey]['tags'] = array_unique(array_merge($groups[$groupKey]['tags'] ?? [], $tags));
        $groups[$groupKey]['operations'][$stubName] = $attributeSource;
        $generated++;
    }
}

$outDir = __DIR__.'/../app/OpenApi/Paths';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
// Clear previously generated files so removed/renamed routes don't leave stale paths behind.
foreach (glob($outDir.'/*.php') ?: [] as $old) {
    unlink($old);
}

foreach ($groups as $groupKey => $group) {
    $className = $groupKey.'Paths';
    $methodsSource = [];
    foreach ($group['operations'] as $stubName => $attributeSource) {
        $methodsSource[] = "    {$attributeSource}\n    private function {$stubName}(): void {}\n";
    }

    $source = "<?php\n\nnamespace App\\OpenApi\\Paths;\n\nuse OpenApi\\Attributes as OA;\n\nclass {$className}\n{\n".implode("\n", $methodsSource)."}\n";

    file_put_contents("{$outDir}/{$className}.php", $source);
}

fwrite(STDOUT, 'Existing hand-written operations: '.count($existing)."\n");
fwrite(STDOUT, "Newly generated operations: {$generated}\n");
fwrite(STDOUT, 'Skipped (no route params found for attribute build): '.count($skipped)."\n");
fwrite(STDOUT, 'Wrote '.count($groups)." path group files to {$outDir}\n");
