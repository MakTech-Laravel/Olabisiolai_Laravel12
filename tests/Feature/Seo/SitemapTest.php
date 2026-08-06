<?php

namespace Tests\Feature\Seo;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionPlan;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\SeoPage;
use App\Services\SitemapService;
use App\Support\EncryptId;
use Database\Seeders\SeoPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapTest extends TestCase
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

        Cache::flush();
    }

    public function test_active_non_flagged_business_appears_with_encrypted_loc_and_lastmod(): void
    {
        $business = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'updated_at' => now()->subDay(),
        ]);

        $xml = $this->generalXml();
        $loc = $this->frontendBase.'/businesses/'.EncryptId::encrypt($business->id);

        $this->assertStringContainsString('<loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>', $xml);
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

        $xml = $this->generalXml();

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

        $xml = $this->generalXml();

        $this->assertStringContainsString(
            '<loc>'.$this->frontendBase.'/catalog/items/'.$included->id.'</loc>',
            $xml,
        );
        $this->assertStringNotContainsString(
            '/catalog/items/'.$excluded->id,
            $xml,
        );
    }

    public function test_static_urls_come_from_seo_pages_not_config_alone(): void
    {
        $this->seed(SeoPageSeeder::class);

        $xml = $this->generalXml();

        foreach (SeoPage::query()->where('noindex', false)->pluck('path') as $path) {
            $expected = $path === '/'
                ? $this->frontendBase.'/'
                : $this->frontendBase.$path;

            $this->assertStringContainsString('<loc>'.$expected.'</loc>', $xml, "Missing seo_pages path {$path}");
        }

        $this->assertStringNotContainsString($this->apiBase.'/', $xml);
        $this->assertStringNotContainsString('http://api.test', $xml);
    }

    public function test_empty_marketplace_still_produces_valid_xml_from_seo_pages(): void
    {
        SeoPage::factory()->create([
            'path' => '/',
            'page_name' => 'Home',
            'meta_title' => 'Home',
            'noindex' => false,
        ]);

        $xml = $this->generalXml();

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('<loc>'.$this->frontendBase.'/</loc>', $xml);
        $this->assertDoesNotMatchRegularExpression('#/businesses/#', $xml);
        $this->assertDoesNotMatchRegularExpression('#/catalog/items/#', $xml);
    }

    public function test_sitemap_http_builds_on_demand_without_disk_file(): void
    {
        SeoPage::factory()->create([
            'path' => '/',
            'page_name' => 'Home',
            'noindex' => false,
        ]);
        BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee($this->frontendBase.'/', false);
        $this->assertTrue(Cache::has('sitemap:general:xml'));
    }

    public function test_seo_page_updated_at_used_as_static_lastmod(): void
    {
        $stamp = now()->subDays(5)->startOfSecond();
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'noindex' => false,
            'updated_at' => $stamp,
        ]);

        $xml = $this->generalXml();
        $loc = '<loc>'.$this->frontendBase.'/about</loc>';
        $pos = strpos($xml, $loc);
        $this->assertNotFalse($pos);
        $snippet = substr($xml, $pos, 400);
        $this->assertStringContainsString('<lastmod>'.$stamp->toAtomString().'</lastmod>', $snippet);
    }

    public function test_noindex_seo_page_excluded_from_sitemap_and_resolve_robots(): void
    {
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'Hidden About',
            'noindex' => true,
        ]);

        $xml = $this->generalXml();
        $this->assertStringNotContainsString($this->frontendBase.'/about</loc>', $xml);

        $resolve = $this->getJson('/api/v1/seo-pages/resolve?path=/about');
        $resolve->assertOk();
        $resolve->assertJsonPath('data.robots', 'noindex,nofollow');
        $resolve->assertJsonPath('data.title', 'Hidden About');
    }

    public function test_sitemap_refresh_warms_cache(): void
    {
        SeoPage::factory()->create([
            'path' => '/',
            'page_name' => 'Home',
            'noindex' => false,
        ]);

        Artisan::call('sitemap:refresh');

        $this->assertTrue(Cache::has('sitemap:general:xml'));
        $this->assertGreaterThan(0, app(SitemapService::class)->urlCount());
    }

    private function generalXml(): string
    {
        Cache::flush();

        return $this->get('/sitemap.xml')->assertOk()->getContent();
    }
}
