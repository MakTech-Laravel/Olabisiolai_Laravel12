<?php

namespace Database\Seeders;

use App\Enums\CmsPageType;
use App\Services\CmsPageService;
use Illuminate\Database\Seeder;

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
            CmsPageType::PrivacyPolicy->value => [
                'title' => 'Privacy Policy',
                'description' => <<<'HTML'
<p>Gidira respects your privacy. This policy explains what information we collect and how we use it.</p>
<h2>Information we collect</h2>
<ul>
<li>Account details such as name, email, and phone number</li>
<li>Profile and business information you choose to provide</li>
<li>Usage data, device information, and cookies needed to operate the service</li>
</ul>
<h2>How we use information</h2>
<p>We use your data to provide the marketplace, improve security, send service-related messages, and comply with legal obligations.</p>
<h2>Sharing</h2>
<p>We do not sell your personal information. We may share data with service providers who help us run the platform, or when required by law.</p>
<h2>Your choices</h2>
<p>You may update profile details in account settings and manage marketing preferences where available.</p>
<p><em>Last updated: May 2026</em></p>
HTML,
            ],
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
