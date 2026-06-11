<?php

namespace App\Contracts\SocialAuth;

use App\Data\SocialAuth\SocialAuthProfile;

interface SocialAuthProviderContract
{
    public function name(): string;

    public function redirectUrl(?string $state = null): string;

    public function profileFromAccessToken(string $accessToken): SocialAuthProfile;

    public function profileFromAuthorizationCode(string $code, ?string $redirectUri = null): SocialAuthProfile;

    public function profileFromIdToken(string $idToken): SocialAuthProfile;
}
