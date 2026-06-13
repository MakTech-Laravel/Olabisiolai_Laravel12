<?php

namespace App\Services\SocialAuth;

use App\Data\SocialAuth\SocialAuthProfile;
use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use App\Services\WelcomeEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthService
{
    public function __construct(
        private readonly SocialAuthManager $manager,
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactor,
        private readonly WelcomeEmailService $welcomeEmail,
    ) {}

    /**
     * @return array{
     *     user: User,
     *     token?: string,
     *     two_factor_required?: bool,
     *     two_factor_token?: string,
     *     is_new_user: bool
     * }
     */
    public function loginOrRegister(SocialAuthProfile $profile, string $role): array
    {
        if (! in_array($role, ['user', 'vendor'], true)) {
            throw ValidationException::withMessages([
                'role' => ['Invalid account role.'],
            ]);
        }

        if (! filled($profile->providerUserId)) {
            throw ValidationException::withMessages([
                'provider' => ['Social account identifier is missing.'],
            ]);
        }

        if (! filled($profile->email)) {
            throw ValidationException::withMessages([
                'email' => ['Your '.$profile->provider.' account did not return an email address.'],
            ]);
        }

        return DB::transaction(function () use ($profile, $role): array {
            $isNewUser = false;

            /** @var SocialAccount|null $socialAccount */
            $socialAccount = SocialAccount::query()
                ->where('provider', $profile->provider)
                ->where('provider_user_id', $profile->providerUserId)
                ->lockForUpdate()
                ->first();

            if ($socialAccount instanceof SocialAccount) {
                $user = $socialAccount->user;
            } else {
                $user = User::query()
                    ->where('email', $profile->email)
                    ->lockForUpdate()
                    ->first();

                if (! $user instanceof User) {
                    $user = $this->createUserFromProfile($profile, $role);
                    $isNewUser = true;
                }

                $this->upsertSocialAccount($user, $profile);
            }

            $this->assertUserCanAuthenticate($user, $role);
            $this->syncVerifiedEmail($user, $profile);
            $this->syncProfileBasics($user, $profile);

            $user = $user->fresh();

            if ($isNewUser && filled($user->email) && $user->isAccountVerified()) {
                $this->welcomeEmail->sendAfterRegistration($user);
            }

            if ($this->twoFactor->isEnabled($user)) {
                $challengeToken = $this->authService->initiateTwoFactorLogin($user, $role);

                return [
                    'user' => $user->fresh(),
                    'two_factor_required' => true,
                    'two_factor_token' => $challengeToken,
                    'is_new_user' => $isNewUser,
                ];
            }

            return [
                'user' => $user->fresh(),
                'token' => $this->authService->issueAccessToken($user),
                'is_new_user' => $isNewUser,
            ];
        });
    }

    private function createUserFromProfile(SocialAuthProfile $profile, string $role): User
    {
        return User::query()->create([
            'first_name' => $profile->resolvedFirstName(),
            'last_name' => $profile->resolvedLastName(),
            'name' => $profile->resolvedName(),
            'email' => $profile->email,
            'role' => $role,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $profile->emailVerified ? now() : null,
            'password' => Str::password(40),
            'settings' => [
                'registration_verification_channel' => 'email',
                'social_signup_provider' => $profile->provider,
            ],
        ]);
    }

    private function upsertSocialAccount(User $user, SocialAuthProfile $profile): SocialAccount
    {
        return SocialAccount::query()->updateOrCreate(
            [
                'provider' => $profile->provider,
                'provider_user_id' => $profile->providerUserId,
            ],
            [
                'user_id' => $user->id,
                'provider_email' => $profile->email,
                'avatar_url' => $profile->avatarUrl,
                'meta' => $profile->meta,
            ],
        );
    }

    private function assertUserCanAuthenticate(User $user, string $role): void
    {
        if ($user->role === 'admin') {
            throw ValidationException::withMessages([
                'provider' => ['Admins must use the admin login URL.'],
            ]);
        }

        if ($user->status === UserStatus::Block->value) {
            throw ValidationException::withMessages([
                'provider' => ['This account has been blocked. Please contact support.'],
            ]);
        }

        if ($user->role !== $role) {
            throw ValidationException::withMessages([
                'role' => ["This account is registered as a {$user->role}. Please log in with role: {$user->role}."],
            ]);
        }
    }

    private function syncVerifiedEmail(User $user, SocialAuthProfile $profile): void
    {
        if (! $profile->emailVerified || ! filled($profile->email)) {
            return;
        }

        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::Active->value,
        ])->save();
    }

    private function syncProfileBasics(User $user, SocialAuthProfile $profile): void
    {
        $updates = [];

        if (! filled($user->first_name) && filled($profile->resolvedFirstName())) {
            $updates['first_name'] = $profile->resolvedFirstName();
        }

        if (! filled($user->last_name) && filled($profile->resolvedLastName())) {
            $updates['last_name'] = $profile->resolvedLastName();
        }

        if (! filled($user->name) && filled($profile->resolvedName())) {
            $updates['name'] = $profile->resolvedName();
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }
}
