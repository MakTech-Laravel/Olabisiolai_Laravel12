<?php

use App\Http\Middleware\AuthenticateApiOptional;
use App\Http\Middleware\EnsureAdminApi;
use App\Http\Middleware\EnsureEmailVerifiedApi;
use App\Http\Middleware\EnsurePurchasesEmailVerified;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureVendorPremiumActive;
use App\Http\Middleware\EnsureVendorSubscriptionActive;
use App\Http\Middleware\SanitizeUploadedFiles;
use App\Http\Middleware\ThrottleMessaging;
use App\Http\Middleware\UpdateUserStatus;
use App\Support\ApiExceptionMessage;
use Fruitcake\Cors\CorsService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['middleware' => ['auth:api,admin_api']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        // Global stack runs before route middleware (auth:api / Passport). Passport converts
        // uploads to PSR-7 and crashes on invalid/empty tmp files — clean them first.
        $middleware->append(SanitizeUploadedFiles::class);
        $middleware->alias([
            'auth.api.optional' => AuthenticateApiOptional::class,
            'verified' => EnsureEmailVerifiedApi::class,
            'purchase.email_verified' => EnsurePurchasesEmailVerified::class,
            'role' => EnsureRole::class,
            'admin' => EnsureAdminApi::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'messaging.throttle' => ThrottleMessaging::class,
            'messaging.presence' => UpdateUserStatus::class,
            'vendor.subscription' => EnsureVendorSubscriptionActive::class,
            'vendor.premium' => EnsureVendorPremiumActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                if ($status >= 400 && $status < 500 && ! ApiExceptionMessage::looksInternal($e->getMessage())) {
                    return null;
                }
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            if ($status < 400) {
                $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => ApiExceptionMessage::for($e),
                'data' => null,
            ], $status);
        });

        /*
         * 500 responses from the exception handler skip the middleware stack, so the browser
         * often reports "CORS blocked" even when the real failure is a server error.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->headers->has('Origin')) {
                return $response;
            }

            foreach (config('cors.paths', []) as $path) {
                if ($request->is($path)) {
                    $cors = app(CorsService::class);
                    $cors->setOptions(config('cors', []));

                    if ($cors->isCorsRequest($request)) {
                        return $cors->addActualRequestHeaders($response, $request);
                    }

                    break;
                }
            }

            return $response;
        });
    })->create();
