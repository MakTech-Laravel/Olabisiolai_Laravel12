<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SocialAuth\SocialAuthProviderContract;
use App\Data\SocialAuth\SocialAuthProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SocialLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\SocialAuth\SocialAuthManager;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthManager $socialAuthManager,
        private readonly SocialAuthService $socialAuthService,
    ) {}

    public function providers()
    {
        return sendResponse(true, 'Enabled social login providers.', [
            'providers' => $this->socialAuthManager->enabledProviders(),
        ]);
    }

    public function redirect(Request $request, string $provider)
    {
        try {
            $driver = $this->socialAuthManager->driver($provider);
            $state = $request->query('state');
            $stateString = is_string($state) ? $state : null;

            return sendResponse(true, 'Social login redirect URL generated.', [
                'provider' => $provider,
                'url' => $driver->redirectUrl($stateString),
            ]);
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_NOT_FOUND);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Unable to start social login.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(SocialLoginRequest $request, string $provider)
    {
        try {
            $driver = $this->socialAuthManager->driver($provider);
            $validated = $request->validated();
            $profile = $this->resolveProfile($driver, $validated, $provider);
            $result = $this->socialAuthService->loginOrRegister($profile, (string) $validated['role']);

            if (($result['two_factor_required'] ?? false) === true) {
                return sendResponse(true, 'Two-factor authentication required.', array_merge([
                    'provider' => $provider,
                    'is_new_user' => $result['is_new_user'],
                    'user' => UserResource::make($result['user']),
                ], [
                    'two_factor_required' => true,
                    'two_factor_token' => $result['two_factor_token'],
                    'verification_status' => 'two_factor_required',
                    'verification_channel' => $result['two_factor_channel'] ?? 'email',
                    'masked_email' => $result['two_factor_masked_email'] ?? null,
                    'masked_phone' => $result['two_factor_masked_phone'] ?? null,
                    'otp' => $result['two_factor_otp']?->code,
                ]));
            }

            return sendResponse(true, 'Social login successful.', [
                'provider' => $provider,
                'token' => $result['token'],
                'verification_status' => 'verified',
                'is_new_user' => $result['is_new_user'],
                'user' => UserResource::make($result['user']),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_NOT_FOUND);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Social login failed. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function callback(Request $request, string $provider)
    {
        try {
            $this->socialAuthManager->driver($provider);

            $code = (string) $request->query('code', '');

            if ($code === '') {
                return sendResponse(false, 'Missing authorization code.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $redirectUri = $this->socialCallbackRedirectUri($provider);
            $role = (string) $request->query('role', 'user');
            if (! in_array($role, ['user', 'vendor'], true)) {
                $role = 'user';
            }

            // Single-use code: client must POST /auth/social/{provider}/login immediately.
            $frontendCallback = config('social_auth.frontend_callback_url');

            if (is_string($frontendCallback) && $frontendCallback !== '') {
                $query = http_build_query([
                    'provider' => $provider,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'role' => $role,
                ]);

                return redirect()->away(rtrim($frontendCallback, '/').'?'.$query);
            }

            return sendResponse(true, 'Authorization code received. Exchange it via POST /api/v1/auth/social/'.$provider.'/login.', [
                'provider' => $provider,
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'role' => $role,
                'exchange_endpoint' => url('/api/v1/auth/social/'.$provider.'/login'),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->validator->errors()->toArray()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (InvalidArgumentException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_NOT_FOUND);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Social login callback failed.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function socialCallbackRedirectUri(string $provider): string
    {
        if ($provider === 'google') {
            return (string) config('services.google.redirect');
        }

        return url('/api/v1/auth/social/'.$provider.'/callback');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveProfile(
        SocialAuthProviderContract $driver,
        array $validated,
        string $provider,
    ): SocialAuthProfile {
        if (filled($validated['id_token'] ?? null)) {
            return $driver->profileFromIdToken((string) $validated['id_token']);
        }

        if (filled($validated['access_token'] ?? null)) {
            return $driver->profileFromAccessToken((string) $validated['access_token']);
        }

        $redirectUri = filled($validated['redirect_uri'] ?? null)
            ? (string) $validated['redirect_uri']
            : $this->socialCallbackRedirectUri($provider);

        return $driver->profileFromAuthorizationCode(
            (string) $validated['code'],
            $redirectUri,
        );
    }
}
