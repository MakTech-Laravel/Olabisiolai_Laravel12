<?php

namespace App\Services\SocialAuth\Providers;

use App\Contracts\SocialAuth\SocialAuthProviderContract;
use App\Data\SocialAuth\SocialAuthProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleSocialAuthProvider implements SocialAuthProviderContract
{
    public function name(): string
    {
        return 'google';
    }

    public function redirectUrl(?string $state = null): string
    {
        $driver = Socialite::driver('google')->stateless();

        if ($state !== null && $state !== '') {
            $driver = $driver->with(['state' => $state]);
        }

        return $driver->redirect()->getTargetUrl();
    }

    public function profileFromAccessToken(string $accessToken): SocialAuthProfile
    {
        $user = Socialite::driver('google')->stateless()->userFromToken($accessToken);

        return $this->mapSocialiteUser($user);
    }

    public function profileFromAuthorizationCode(string $code, ?string $redirectUri = null): SocialAuthProfile
    {
        $driver = Socialite::driver('google')->stateless();
        $callbackUri = filled($redirectUri)
            ? (string) $redirectUri
            : (string) config('services.google.redirect');

        if ($callbackUri !== '') {
            $driver->redirectUrl($callbackUri);
        }

        try {
            $request = Request::create('/', 'GET', ['code' => $code]);
            $user = $driver->setRequest($request)->user();

            return $this->mapSocialiteUser($user);
        } catch (\Throwable $throwable) {
            throw ValidationException::withMessages([
                'code' => [
                    'Google authorization code is invalid or expired. Request a new code from the redirect URL and exchange it immediately via POST /auth/social/google/login.',
                ],
            ]);
        }
    }

    public function profileFromIdToken(string $idToken): SocialAuthProfile
    {
        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (! $response->successful()) {
            $hint = 'Use a real JWT from Google Sign-In — the placeholder "GOOGLE_ID_TOKEN" will not work.';
            if (config('app.debug')) {
                $googleError = $response->json('error_description') ?? $response->json('error');
                if (is_string($googleError) && $googleError !== '') {
                    $hint = "Google rejected this ID token: {$googleError}";
                }
            }

            throw ValidationException::withMessages([
                'id_token' => [$hint],
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();
        $clientId = (string) config('services.google.client_id');

        if ($clientId !== '' && (($payload['aud'] ?? null) !== $clientId)) {
            throw ValidationException::withMessages([
                'id_token' => ['Google ID token audience does not match this application.'],
            ]);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' && ($payload['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'id_token' => ['Google account email is not verified.'],
            ]);
        }

        $providerUserId = (string) ($payload['sub'] ?? '');
        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'id_token' => ['Google ID token is missing a subject identifier.'],
            ]);
        }

        $email = isset($payload['email']) ? strtolower((string) $payload['email']) : null;
        $name = isset($payload['name']) ? (string) $payload['name'] : null;

        return new SocialAuthProfile(
            provider: $this->name(),
            providerUserId: $providerUserId,
            email: $email,
            name: $name,
            firstName: isset($payload['given_name']) ? (string) $payload['given_name'] : null,
            lastName: isset($payload['family_name']) ? (string) $payload['family_name'] : null,
            avatarUrl: isset($payload['picture']) ? (string) $payload['picture'] : null,
            emailVerified: true,
            meta: $payload,
        );
    }

    private function mapSocialiteUser(SocialiteUser $user): SocialAuthProfile
    {
        $raw = is_array($user->getRaw()) ? $user->getRaw() : [];

        return new SocialAuthProfile(
            provider: $this->name(),
            providerUserId: (string) $user->getId(),
            email: $user->getEmail() ? strtolower((string) $user->getEmail()) : null,
            name: $user->getName(),
            firstName: isset($raw['given_name']) ? (string) $raw['given_name'] : null,
            lastName: isset($raw['family_name']) ? (string) $raw['family_name'] : null,
            avatarUrl: $user->getAvatar(),
            emailVerified: filter_var($raw['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN),
            meta: $raw,
        );
    }
}
