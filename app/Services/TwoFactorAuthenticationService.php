<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && filled($user->two_factor_secret);
    }

    /**
     * @return array{secret: string, qr_code: string}
     */
    public function enable(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'Gidira'),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'qr_code' => $this->renderQrCodeSvg($otpauthUrl),
            'otpauth_url' => $otpauthUrl,
        ];
    }

    /**
     * @return array{recovery_codes: list<string>}
     */
    public function confirm(User $user, string $code): array
    {
        if (! $this->verifyCode($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return ['recovery_codes' => $recoveryCodes];
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verify(User $user, string $code): bool
    {
        return $this->verifyCode($user, $code) || $this->verifyRecoveryCode($user, $code);
    }

    public function verifyCode(User $user, string $code): bool
    {
        $secret = $this->decryptedSecret($user);
        if ($secret === null || $secret === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $code) ?? $code;

        return $this->google2fa->verifyKey($secret, $normalized);
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->recoveryCodes($user);
        $normalized = Str::lower(trim($code));

        foreach ($codes as $index => $stored) {
            if (hash_equals(Str::lower($stored), $normalized)) {
                unset($codes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($codes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(User $user): array
    {
        if (! filled($user->two_factor_recovery_codes)) {
            return [];
        }

        $decoded = $user->two_factor_recovery_codes;
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn($c) => is_string($c) && $c !== ''));
    }

    private function renderQrCodeSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);

        return $writer->writeString($otpauthUrl);
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::upper(Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }

    private function decryptedSecret(User $user): ?string
    {
        $secret = $user->two_factor_secret;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
