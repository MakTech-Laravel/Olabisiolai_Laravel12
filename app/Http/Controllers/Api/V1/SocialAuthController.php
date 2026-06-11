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

            $profile = $this->resolveProfile($driver, $validated);
            $result = $this->socialAuthService->loginOrRegister($profile, (string) $validated['role']);

            if (($result['two_factor_required'] ?? false) === true) {
                return sendResponse(true, 'Two-factor authentication required.', [
                    'provider' => $provider,
                    'two_factor_required' => true,
                    'two_factor_token' => $result['two_factor_token'],
                    'verification_status' => 'two_factor_required',
                    'is_new_user' => $result['is_new_user'],
                    'user' => UserResource::make($result['user']),
                ]);
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
        // dd($request->all());
        try {
            $driver = $this->socialAuthManager->driver($provider);
            $code = (string) $request->query('code', '');

            if ($code === '') {
                return sendResponse(false, 'Missing authorization code.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $role = (string) $request->query('role', 'user');
            if (! in_array($role, ['user', 'vendor'], true)) {
                $role = 'user';
            }

            $profile = $driver->profileFromAuthorizationCode(
                $code,
                is_string($request->query('redirect_uri')) ? $request->query('redirect_uri') : null,
            );

            $result = $this->socialAuthService->loginOrRegister($profile, $role);
            $frontendCallback = config('social_auth.frontend_callback_url') ?: config('app.frontend_url');

            if (! is_string($frontendCallback) || $frontendCallback === '') {
                return sendResponse(true, 'Social login successful.', [
                    'provider' => $provider,
                    'token' => $result['token'] ?? null,
                    'two_factor_required' => $result['two_factor_required'] ?? false,
                    'two_factor_token' => $result['two_factor_token'] ?? null,
                    'is_new_user' => $result['is_new_user'],
                    'user' => UserResource::make($result['user']),
                ]);
            }

            $query = http_build_query(array_filter([
                'provider' => $provider,
                'token' => $result['token'] ?? null,
                'two_factor_required' => ($result['two_factor_required'] ?? false) ? '1' : '0',
                'two_factor_token' => $result['two_factor_token'] ?? null,
                'is_new_user' => $result['is_new_user'] ? '1' : '0',
            ]));

            // return redirect()->away(rtrim($frontendCallback, '/').'?'.$query);
            return sendResponse(true, 'Social login successful.', [
                'query' => $query,
                // 'provider' => $provider,
                // 'code' => $code,
                // 'token' => $result['token'] ?? null,
                // 'role' => $role,
                // 'profile' => $profile,
                // 'result' => $result,
                // 'frontendCallback' => $frontendCallback,
                // 'query' => $query,
                // 'two_factor_required' => $result['two_factor_required'] ?? false,
                // 'two_factor_token' => $result['two_factor_token'] ?? null,
                // 'is_new_user' => $result['is_new_user'],
                // 'user' => UserResource::make($result['user']),
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveProfile(SocialAuthProviderContract $driver, array $validated): SocialAuthProfile
    {
        if (filled($validated['id_token'] ?? null)) {
            return $driver->profileFromIdToken((string) $validated['id_token']);
        }

        if (filled($validated['access_token'] ?? null)) {
            return $driver->profileFromAccessToken((string) $validated['access_token']);
        }

        return $driver->profileFromAuthorizationCode(
            (string) $validated['code'],
            isset($validated['redirect_uri']) ? (string) $validated['redirect_uri'] : null,
        );
    }
}
