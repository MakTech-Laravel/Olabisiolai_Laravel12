<?php

/**
 * Shared route + FormRequest/inline-validation introspection used by both
 * scripts/generate-postman-collection.php and scripts/generate-openapi-docs.php.
 *
 * Boots the app once, then exposes: resolveRulesForAction() to get the raw Laravel
 * validation rules array for a controller action (from a FormRequest, an inline
 * `$request->validate([...])` call, or null), plus small route-classification helpers
 * both generators use to group/auth/path-param routes the same way.
 */

require_once __DIR__.'/../../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// ---------------------------------------------------------------------------
// Rule token helpers
// ---------------------------------------------------------------------------

/**
 * @return list<string>
 */
function ruleTokens(array $rules): array
{
    $tokens = [];
    foreach ($rules as $rule) {
        if (is_string($rule)) {
            $tokens[] = $rule;

            continue;
        }
        if ($rule instanceof Closure) {
            continue;
        }
        try {
            $s = (string) $rule;
            if ($s !== '') {
                $tokens[] = $s;
            }
        } catch (Throwable $e) {
            $tokens[] = 'class:'.get_class($rule);
        }
    }

    return $tokens;
}

/**
 * @return list<string>
 */
function parseInList(string $token): array
{
    // token looks like: in:"a","b","c"
    if (! str_starts_with($token, 'in:')) {
        return [];
    }
    $body = substr($token, 3);
    preg_match_all('/"([^"]*)"/', $body, $m);
    if ($m[1]) {
        return $m[1];
    }

    // Rule::in on a backed enum sometimes stringifies without quotes: in:a,b,c
    return array_values(array_filter(explode(',', $body)));
}

function isMultipartRules(array $rules): bool
{
    foreach ($rules as $ruleset) {
        foreach ((array) $ruleset as $rule) {
            if (is_string($rule) && (str_contains($rule, 'file') || str_contains($rule, 'image') || str_contains($rule, 'mimes'))) {
                return true;
            }
        }
    }

    return false;
}

// ---------------------------------------------------------------------------
// FormRequest / inline validate() resolution
// ---------------------------------------------------------------------------

/**
 * @return array{0: string, 1: list<string>}
 */
function fileHeaderImports(string $filename): array
{
    static $cache = [];
    if (isset($cache[$filename])) {
        return $cache[$filename];
    }

    $namespace = '';
    $uses = [];
    foreach (file($filename) as $line) {
        $trimmed = trim($line);
        if (preg_match('/^namespace\s+([^;]+);/', $trimmed, $m)) {
            $namespace = $m[1];
        } elseif (preg_match('/^use\s+([^;]+);/', $trimmed, $m)) {
            $uses[] = $m[1];
        } elseif (preg_match('/^(final\s+|abstract\s+)?class\s/', $trimmed)) {
            break;
        }
    }

    return $cache[$filename] = [$namespace, $uses];
}

/**
 * Find the index of the char that closes the bracket/paren opened at $openPos, counting
 * both `(`/`)` and `[`/`]` together (rule arrays nest `Rule::in([...])` inside `[...]`).
 */
function findMatchingClose(string $src, int $openPos): ?int
{
    $depth = 0;
    for ($i = $openPos, $len = strlen($src); $i < $len; $i++) {
        $ch = $src[$i];
        if ($ch === '(' || $ch === '[') {
            $depth++;
        } elseif ($ch === ')' || $ch === ']') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

/**
 * Many controllers here validate inline (`$request->validate([...])`) instead of via a
 * dedicated FormRequest. We extract *only* the rules array literal (never the surrounding
 * method body — it often has early-return guard clauses like `adminAuthCheck($request)`
 * that would short-circuit against our synthetic empty request before ever reaching
 * validate()). For rules that reference a preceding local variable, e.g.
 * `$durations = $this->service->dynamicDurations();` then `Rule::in($durations)`, we pull in
 * just that one assignment statement — never the rest of the method — so guard clauses are
 * never executed. Eval'd in the controller's original namespace/imports and with `$this`
 * bound to a real (container-resolved) controller instance, so `Rule::in(...)`, enum
 * ::values(), and `$this->service->...` calls resolve correctly.
 *
 * @return array<string, mixed>|null
 */
function extractInlineValidateRules(string $controller, string $method, int $depthGuard = 0): ?array
{
    if ($depthGuard > 3) {
        return null;
    }

    try {
        $reflection = new ReflectionMethod($controller, $method);
        $filename = $reflection->getFileName();
        if ($filename === false) {
            return null;
        }
        $lines = file($filename);
        $methodSource = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    } catch (Throwable $e) {
        return null;
    }

    if (! preg_match('/->validate\s*\(\s*\[/', $methodSource, $m, PREG_OFFSET_CAPTURE)) {
        // No inline validate() here — maybe it delegates to another same-class method, e.g.
        // `$validated = $this->validateDynamicBoostPayload($request);`
        if (preg_match('/\$this->(\w+)\(\s*\$request\s*\)/', $methodSource, $dm)) {
            $delegate = $dm[1];
            if ($delegate !== $method && method_exists($controller, $delegate)) {
                return extractInlineValidateRules($controller, $delegate, $depthGuard + 1);
            }
        }

        return null;
    }

    $bracketStart = $m[0][1] + strlen($m[0][0]) - 1;
    $endPos = findMatchingClose($methodSource, $bracketStart);
    if ($endPos === null) {
        return null;
    }

    $arrayLiteral = substr($methodSource, $bracketStart, $endPos - $bracketStart + 1);

    // Pull in only the specific preceding "$var = ...;" assignments the array literal
    // actually references (e.g. Rule::in($durations)) — not any other surrounding code.
    preg_match_all('/\$(\w+)/', $arrayLiteral, $varRefs);
    $neededVars = array_unique(array_diff($varRefs[1], ['request']));
    $precedingSource = substr($methodSource, 0, $bracketStart);
    $assignments = [];
    foreach ($neededVars as $varName) {
        if (preg_match_all('/\$'.preg_quote($varName, '/').'\s*=[^=][^;]*;/', $precedingSource, $am) && $am[0]) {
            $assignments[] = end($am[0]);
        }
    }

    [$namespace, $uses] = fileHeaderImports($filename);
    $useStatements = implode("\n", array_map(fn (string $u) => "use {$u};", $uses));
    $assignmentSource = implode("\n", $assignments);

    $code = "namespace {$namespace};\n{$useStatements}\nreturn function (\\Illuminate\\Http\\Request \$request) {\n{$assignmentSource}\nreturn {$arrayLiteral};\n};";

    try {
        $closureFactory = eval($code);
        if (! $closureFactory instanceof Closure) {
            return null;
        }
        $controllerInstance = app($controller);
        $bound = Closure::bind($closureFactory, $controllerInstance, $controller);
        $result = $bound(new Request);
    } catch (Throwable $e) {
        return null;
    }

    return is_array($result) ? $result : null;
}

/**
 * Resolve the raw Laravel validation rules array for a controller action, whether it comes
 * from a type-hinted FormRequest or an inline `$request->validate([...])` call.
 *
 * @return array{0: array<string, mixed>, 1: bool} [rules, isMultipart]
 */
function resolveRulesForAction(string $controller, string $method): array
{
    if (! class_exists($controller) || ! method_exists($controller, $method)) {
        return [[], false];
    }

    try {
        $reflection = new ReflectionMethod($controller, $method);
    } catch (Throwable $e) {
        return [[], false];
    }

    foreach ($reflection->getParameters() as $param) {
        $type = $param->getType();
        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            continue;
        }

        $class = $type->getName();
        if (! is_subclass_of($class, FormRequest::class)) {
            continue;
        }

        try {
            /** @var FormRequest $formRequest */
            $formRequest = new $class;
            $formRequest->setContainer(app());
            $rules = $formRequest->rules();
        } catch (Throwable $e) {
            return [[], false];
        }

        return [$rules, isMultipartRules($rules)];
    }

    $inlineRules = extractInlineValidateRules($controller, $method);
    if ($inlineRules !== null) {
        return [$inlineRules, isMultipartRules($inlineRules)];
    }

    return [[], false];
}

// ---------------------------------------------------------------------------
// Route classification shared by both generators
// ---------------------------------------------------------------------------

function authKindFor(array $middleware): ?string
{
    $flat = implode('|', $middleware);

    if (str_contains($flat, 'auth:admin_api') && ! str_contains($flat, 'auth:api,admin_api')) {
        return 'admin';
    }

    if (str_contains($flat, 'auth:api') || str_contains($flat, 'auth:api,admin_api')) {
        return 'user';
    }

    return null;
}

function pathVariableFor(string $paramName): string
{
    $lower = Str::lower($paramName);

    return match (true) {
        str_contains($lower, 'business') => 'business_id',
        str_contains($lower, 'review') => 'review_id',
        str_contains($lower, 'conversation') => 'conversation_id',
        str_contains($lower, 'message') => 'message_id',
        default => 'id',
    };
}
