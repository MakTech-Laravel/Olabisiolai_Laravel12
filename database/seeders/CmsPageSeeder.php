<?php

namespace Database\Seeders;

use App\Enums\CmsPageType;
use App\Services\CmsPageService;
use Illuminate\Database\Seeder;
use RuntimeException;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $cms = app(CmsPageService::class);

        foreach (self::pages() as $typeValue => $page) {
            $type = CmsPageType::from($typeValue);
            $cms->upsertByType($type, $page['title'], $page['description']);
        }
    }

    /**
     * @return array{title: string, description: string}
     */
    public static function privacyPolicyPage(): array
    {
        return [
            'title' => 'Privacy Policy',
            'description' => self::privacyPolicyHtml(),
        ];
    }

    /**
     * @return array{title: string, description: string}
     */
    public static function deleteAccountPage(): array
    {
        return [
            'title' => 'Delete Account',
            'description' => self::deleteAccountHtml(),
        ];
    }

    public static function privacyPolicyHtml(): string
    {
        $path = database_path('seeders/data/privacy-policy.html');
        $html = is_file($path) ? file_get_contents($path) : false;

        if ($html === false || trim($html) === '') {
            throw new RuntimeException('Privacy policy HTML is missing at '.$path);
        }

        return $html;
    }

    public static function deleteAccountHtml(): string
    {
        $path = database_path('seeders/data/delete-account.html');
        $html = is_file($path) ? file_get_contents($path) : false;

        if ($html === false || trim($html) === '') {
            throw new RuntimeException('Delete account HTML is missing at '.$path);
        }

        return $html;
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    public static function pages(): array
    {
        return [
            CmsPageType::TermsAndConditions->value => [
                'title' => 'Terms and Conditions',
                'description' => <<<'HTML'
<p>Welcome to Gidira. By accessing or using our platform, you agree to these Terms and Conditions.</p>
<h2>1. Use of the platform</h2>
<p>You may browse vendors, request quotes, and communicate through Gidira in accordance with applicable laws and our community guidelines.</p>
<h2>2. Accounts</h2>
<p>You are responsible for keeping your login credentials secure and for all activity under your account.</p>
<h2>3. Vendor listings</h2>
<p>Business information, pricing, and availability displayed on Gidira are provided by vendors. Gidira does not guarantee the accuracy of every listing.</p>
<h2>4. Payments and bookings</h2>
<p>Where payments or bookings are enabled, additional terms shown at checkout apply.</p>
<h2>5. Changes</h2>
<p>We may update these terms from time to time. Continued use of the platform after changes constitutes acceptance.</p>
<p><em>Last updated: May 2026</em></p>
HTML,
            ],
            CmsPageType::PrivacyPolicy->value => self::privacyPolicyPage(),
            CmsPageType::DeleteAccount->value => self::deleteAccountPage(),
            CmsPageType::AboutUs->value => [
                'title' => 'About Gidira',
                'description' => <<<'HTML'
<p><strong>FIND BETTER | CONNECT FASTER</strong></p>
<p>Gidira is a marketplace built for Nigeria's digital economy—helping customers discover trusted vendors and helping businesses grow online.</p>
<h2>Our mission</h2>
<p>We connect people who need services with professionals who deliver them, with clear profiles, messaging, and reviews.</p>
<h2>For customers</h2>
<p>Search by category and location, compare businesses, save favorites, and reach vendors directly.</p>
<h2>For vendors</h2>
<p>Create a business profile, showcase your work, receive enquiries, and build reputation through verified reviews.</p>
<p>Questions? Visit our Contact page—we are happy to help.</p>
HTML,
            ],
        ];
    }
}
