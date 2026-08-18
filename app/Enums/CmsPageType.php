<?php

namespace App\Enums;

enum CmsPageType: string
{
    case TermsAndConditions = 'terms_and_conditions';
    case PrivacyPolicy = 'privacy_policy';
    case AboutUs = 'about_us';
    case DeleteAccount = 'delete_account';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::TermsAndConditions => 'Terms and Conditions',
            self::PrivacyPolicy => 'Privacy Policy',
            self::AboutUs => 'About Us',
            self::DeleteAccount => 'Delete Account',
        };
    }

    public function publicSlug(): string
    {
        return match ($this) {
            self::AboutUs => 'about',
            self::PrivacyPolicy => 'privacy-policy',
            self::TermsAndConditions => 'terms',
            self::DeleteAccount => 'delete-account',
        };
    }

    public static function fromPublicSlug(string $slug): ?self
    {
        return match ($slug) {
            'about' => self::AboutUs,
            'privacy-policy' => self::PrivacyPolicy,
            'terms' => self::TermsAndConditions,
            'delete-account' => self::DeleteAccount,
            default => null,
        };
    }
}
