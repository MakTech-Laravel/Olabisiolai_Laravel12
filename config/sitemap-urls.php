<?php

/**
 * Static SPA paths for the public sitemap.
 * Paths are relative to FRONTEND_URL (not APP_URL).
 *
 * Optional keys:
 * - cms_type: CmsPageType value — when set, lastmod prefers CmsPage.updated_at
 *
 * @return list<array{path: string, changefreq: string, priority: float, cms_type?: string}>
 */
return [
    ['path' => '/', 'changefreq' => 'daily', 'priority' => 1.0],
    ['path' => '/about', 'changefreq' => 'weekly', 'priority' => 0.8, 'cms_type' => 'about_us'],
    ['path' => '/contact', 'changefreq' => 'weekly', 'priority' => 0.7],
    ['path' => '/faq', 'changefreq' => 'weekly', 'priority' => 0.7],
    ['path' => '/terms', 'changefreq' => 'weekly', 'priority' => 0.7, 'cms_type' => 'terms_and_conditions'],
    ['path' => '/privacy-policy', 'changefreq' => 'weekly', 'priority' => 0.7, 'cms_type' => 'privacy_policy'],
    ['path' => '/delete-account', 'changefreq' => 'weekly', 'priority' => 0.7, 'cms_type' => 'delete_account'],
    ['path' => '/cookies-policy', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/community-guidelines', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/vendor-agreement', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/refund-policy', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/careers', 'changefreq' => 'weekly', 'priority' => 0.7],
    ['path' => '/business-tips', 'changefreq' => 'weekly', 'priority' => 0.7],
    ['path' => '/business-tips/photos-that-sell', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/business-tips/writing-a-compelling-description', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/business-tips/getting-more-positive-reviews', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/business-tips/responding-to-customer-enquiries', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/business-tips/marketing-beyond-gidira', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/business-tips/pricing-your-services-right', 'changefreq' => 'weekly', 'priority' => 0.6],
    ['path' => '/catalog', 'changefreq' => 'daily', 'priority' => 0.9],
    ['path' => '/filters', 'changefreq' => 'daily', 'priority' => 0.8],
    ['path' => '/vendor/choose-your-plan', 'changefreq' => 'weekly', 'priority' => 0.7],
    ['path' => '/vendor/premium-info', 'changefreq' => 'weekly', 'priority' => 0.7],
];
