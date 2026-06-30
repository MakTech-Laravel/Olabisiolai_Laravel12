<?php

namespace App\Services;

use App\Models\Admin;
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

    public function isEnabled(User|Admin $account): bool
    {
        return $account->two_factor_confirmed_at !== null
            && filled($account->two_factor_secret);
    }

    /**
     * @return array{secret: string, qr_code: string}
     */
    public function enable(User|Admin $account): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $account->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'Gidira'),
            (string) $account->email,
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
    public function confirm(User|Admin $account, string $code): array
    {
        if (! $this->verifyCode($account, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $account->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return ['recovery_codes' => $recoveryCodes];
    }

    public function disable(User|Admin $account): void
    {
        $account->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verify(User|Admin $account, string $code): bool
    {
        return $this->verifyCode($account, $code) || $this->verifyRecoveryCode($account, $code);
    }

    public function verifyCode(User|Admin $account, string $code): bool
    {
        $secret = $this->decryptedSecret($account);
        if ($secret === null || $secret === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $code) ?? $code;

        return $this->google2fa->verifyKey($secret, $normalized, 2);
    }

    public function verifyRecoveryCode(User|Admin $account, string $code): bool
    {
        $codes = $this->recoveryCodes($account);
        $normalized = Str::lower(trim($code));

        foreach ($codes as $index => $stored) {
            if (hash_equals(Str::lower($stored), $normalized)) {
                unset($codes[$index]);
                $account->forceFill([
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
    public function recoveryCodes(User|Admin $account): array
    {
        if (! filled($account->two_factor_recovery_codes)) {
            return [];
        }

        $decoded = $account->two_factor_recovery_codes;
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

    private function decryptedSecret(User|Admin $account): ?string
    {
        $secret = $account->two_factor_secret;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
