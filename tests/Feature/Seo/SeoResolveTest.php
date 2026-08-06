<?php

namespace Tests\Feature\Seo;

use App\Enums\BusinessStatus;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\SeoPage;
use App\Support\EncryptId;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SeoResolveTest extends TestCase
{
    use RefreshDatabase;

    private string $frontendBase = 'https://www.frontend.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => $this->frontendBase,
            'seo.og_default_image' => 'https://cdn.test/default-og.png',
        ]);

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_resolve_static_seo_page(): void
    {
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'About Gidira',
            'meta_description' => 'Learn about us.',
            'meta_keywords' => 'about',
            'noindex' => false,
            'canonical_url' => null,
            'og_image' => 'https://cdn.test/about.png',
        ]);

        $response = $this->getJson('/api/v1/seo-pages/resolve?path=/about');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.matched_entity', 'static');
        $response->assertJsonPath('data.title', 'About Gidira');
        $response->assertJsonPath('data.robots', 'index,follow');
        $response->assertJsonPath('data.canonical', $this->frontendBase.'/about');
        $response->assertJsonPath('data.og.image', 'https://cdn.test/about.png');
        $response->assertJsonPath('data.twitter.card', 'summary_large_image');
    }

    public function test_resolve_home_includes_organization_json_ld(): void
    {
        SeoPage::factory()->create([
            'path' => '/',
            'page_name' => 'Home',
            'meta_title' => 'Gidira Home',
        ]);

        $response = $this->getJson('/api/v1/seo-pages/resolve?path=/');
        $response->assertOk();
        $jsonLd = $response->json('data.json_ld');
        $this->assertIsArray($jsonLd);
        $this->assertNotEmpty($jsonLd);
        $types = array_column($jsonLd, '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_resolve_noindex_and_canonical_override(): void
    {
        SeoPage::factory()->create([
            'path' => '/careers',
            'page_name' => 'Careers',
            'meta_title' => 'Careers',
            'noindex' => true,
            'canonical_url' => 'https://www.frontend.test/jobs',
        ]);

        $response = $this->getJson('/api/v1/seo-pages/resolve?path=/careers');
        $response->assertOk();
        $response->assertJsonPath('data.robots', 'noindex,nofollow');
        $response->assertJsonPath('data.canonical', 'https://www.frontend.test/jobs');
    }

    public function test_resolve_business_local_business_json_ld(): void
    {
        $business = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'business_name' => 'Acme Plumbing',
            'business_description' => 'Pipes fixed fast.',
        ]);

        $path = '/businesses/'.EncryptId::encrypt($business->id);
        $response = $this->getJson('/api/v1/seo-pages/resolve?path='.$path);
        $response->assertOk();
        $response->assertJsonPath('data.matched_entity', 'business');
        $response->assertJsonPath('data.title', 'Acme Plumbing | Gidira');
        $response->assertJsonPath('data.json_ld.0.@type', 'LocalBusiness');
        $response->assertJsonPath('data.canonical', $this->frontendBase.$path);
    }

    public function test_resolve_catalog_item(): void
    {
        $premium = BusinessInfo::factory()->premiumActive()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);
        $item = BusinessCatalogItem::query()->create([
            'business_info_id' => $premium->id,
            'type' => 'service',
            'name' => 'Emergency Fix',
            'description' => 'Same day',
            'price_kobo' => 100000,
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/seo-pages/resolve?path=/catalog/items/'.$item->id);
        $response->assertOk();
        $response->assertJsonPath('data.matched_entity', 'catalog_item');
        $response->assertJsonPath('data.title', 'Emergency Fix | Gidira');
        $response->assertJsonPath('data.json_ld.0.@type', 'Service');
    }

    public function test_resolve_unknown_path_returns_defaults(): void
    {
        $response = $this->getJson('/api/v1/seo-pages/resolve?path=/no-such');
        $response->assertOk();
        $response->assertJsonPath('data.matched_entity', null);
        $response->assertJsonPath('data.title', 'Gidira');
        $response->assertJsonPath('data.robots', 'index,follow');
    }

    public function test_robots_txt_is_dynamic_and_points_at_frontend_sitemap(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $body = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: '.$this->frontendBase.'/sitemap.xml', $body);
    }
}
