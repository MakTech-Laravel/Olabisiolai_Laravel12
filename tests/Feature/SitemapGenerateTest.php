<?php

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Enums\CmsPageType;
use App\Enums\SubscriptionPlan;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\CmsPage;
use App\Models\SeoPage;
use App\Services\SitemapService;
use App\Support\EncryptId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SitemapGenerateTest extends TestCase
{
    use RefreshDatabase;

    private string $frontendBase = 'https://www.frontend.test';

    private string $apiBase = 'http://api.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => $this->frontendBase,
            'app.url' => $this->apiBase,
        ]);

        File::deleteDirectory(storage_path('app/sitemap'));
    }

    public function test_active_non_flagged_business_appears_with_encrypted_loc_and_lastmod(): void
    {
        $business = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'updated_at' => now()->subDay(),
        ]);

        Artisan::call('sitemap:generate');

        $xml = $this->generatedXml();
        $loc = $this->frontendBase.'/businesses/'.EncryptId::encrypt($business->id);

        $this->assertStringContainsString('<loc>'. htmlspecialchars($loc, ENT_XML1).'</loc>', $xml);
        $this->assertStringContainsString(
            '<lastmod>'.$business->fresh()->updated_at->toAtomString().'</lastmod>',
            $xml,
        );
        $this->assertStringNotContainsString($this->apiBase.'/businesses/', $xml);
    }

    public function test_flagged_and_inactive_businesses_are_excluded(): void
    {
        $flagged = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => true,
        ]);
        $inactive = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Inactive,
            'is_flagged' => false,
        ]);

        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();

        $this->assertStringNotContainsString(
            '/businesses/'.EncryptId::encrypt($flagged->id),
            $xml,
        );
        $this->assertStringNotContainsString(
            '/businesses/'.EncryptId::encrypt($inactive->id),
            $xml,
        );
    }

    public function test_catalog_item_included_only_when_premium_active_parent(): void
    {
        $premiumBusiness = BusinessInfo::factory()->premiumActive()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);
        $freeBusiness = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);
        $this->assertSame(SubscriptionPlan::Free, $freeBusiness->fresh()->subscription->plan);

        $included = BusinessCatalogItem::query()->create([
            'business_info_id' => $premiumBusiness->id,
            'type' => 'service',
            'name' => 'Premium Service',
            'description' => 'Included',
            'price_kobo' => 500000,
            'sort_order' => 0,
        ]);
        $excluded = BusinessCatalogItem::query()->create([
            'business_info_id' => $freeBusiness->id,
            'type' => 'service',
            'name' => 'Free Service',
            'description' => 'Excluded',
            'price_kobo' => 100000,
            'sort_order' => 0,
        ]);

        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();

        $this->assertStringContainsString(
            '<loc>'.$this->frontendBase.'/catalog/items/'.$included->id.'</loc>',
            $xml,
        );
        $this->assertStringNotContainsString(
            '/catalog/items/'.$excluded->id,
            $xml,
        );
    }

    public function test_all_static_config_paths_present_with_frontend_url(): void
    {
        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();

        foreach (config('sitemap-urls') as $entry) {
            $path = $entry['path'];
            $expected = $path === '/'
                ? $this->frontendBase.'/'
                : $this->frontendBase.$path;

            $this->assertStringContainsString('<loc>'.$expected.'</loc>', $xml, "Missing static path {$path}");
        }

        $this->assertStringNotContainsString($this->apiBase.'/', $xml);
        $this->assertStringNotContainsString('http://api.test', $xml);
    }

    public function test_empty_state_still_produces_valid_xml_with_static_urls(): void
    {
        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('<loc>'.$this->frontendBase.'/</loc>', $xml);
        $this->assertDoesNotMatchRegularExpression('#/businesses/#', $xml);
        $this->assertDoesNotMatchRegularExpression('#/catalog/items/#', $xml);
    }

    public function test_sitemap_http_serves_generated_file(): void
    {
        BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        Artisan::call('sitemap:generate');

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee($this->frontendBase.'/', false);
    }

    public function test_sitemap_http_empty_fallback_when_file_missing(): void
    {
        File::deleteDirectory(storage_path('app/sitemap'));
        app(SitemapService::class)->forgetResponseCache();

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertSee(app(SitemapService::class)->emptyUrlsetXml(), false);
    }

    public function test_cms_page_lastmod_used_for_mapped_static_paths(): void
    {
        $updatedAt = now()->subDays(3)->startOfSecond();
        CmsPage::factory()->type(CmsPageType::AboutUs)->create([
            'updated_at' => $updatedAt,
        ]);

        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();

        $aboutLoc = '<loc>'.$this->frontendBase.'/about</loc>';
        $pos = strpos($xml, $aboutLoc);
        $this->assertNotFalse($pos);
        $snippet = substr($xml, $pos, 400);
        $this->assertStringContainsString('<lastmod>'.$updatedAt->toAtomString().'</lastmod>', $snippet);
    }

    public function test_noindex_seo_page_excluded_from_sitemap_and_resolve_robots(): void
    {
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'Hidden About',
            'noindex' => true,
        ]);

        Artisan::call('sitemap:generate');
        $xml = $this->generatedXml();
        $this->assertStringNotContainsString($this->frontendBase.'/about</loc>', $xml);

        $resolve = $this->getJson('/api/v1/seo-pages/resolve?path=/about');
        $resolve->assertOk();
        $resolve->assertJsonPath('data.robots', 'noindex,nofollow');
        $resolve->assertJsonPath('data.title', 'Hidden About');
    }

    private function generatedXml(): string
    {
        $path = app(SitemapService::class)->path();
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
