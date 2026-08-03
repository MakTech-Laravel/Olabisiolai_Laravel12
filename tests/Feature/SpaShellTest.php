<?php

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Models\Admin;
use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\SeoPage;
use App\Services\SeoPageService;
use App\Support\EncryptId;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpaShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://www.frontend.test',
            'seo.spa_shell_index_path' => resource_path('spa/index.html'),
            'seo.spa_shell_cache_ttl' => 3600,
            'seo.spa_shell_template_fetch' => false,
        ]);

        Cache::flush();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_spa_shell_injects_meta_for_known_path(): void
    {
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'About Gidira',
            'meta_description' => 'Learn about Gidira.',
            'meta_keywords' => 'gidira, about',
        ]);

        $response = $this->get('/spa-shell?path=/about');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('<title>About Gidira</title>', false);
        $response->assertSee('name="description" content="Learn about Gidira."', false);
        $response->assertSee('property="og:title" content="About Gidira"', false);
        $response->assertSee('property="og:description" content="Learn about Gidira."', false);
        $response->assertSee('property="og:site_name" content="Gidira"', false);
        $response->assertSee('name="twitter:card" content="summary"', false);
        $response->assertSee('https://www.frontend.test/about', false);
    }

    public function test_spa_shell_returns_default_title_when_path_unknown(): void
    {
        $response = $this->get('/spa-shell?path=/no-such-page');
        $response->assertOk();
        $response->assertSee('<title>Gidira</title>', false);
        $response->assertDontSee('gidira-seo-start', false);
    }

    public function test_admin_seo_update_invalidates_shell_cache(): void
    {
        $page = SeoPage::factory()->create([
            'path' => '/faq',
            'page_name' => 'FAQ',
            'meta_title' => 'Old FAQ',
            'meta_description' => 'Old desc',
        ]);

        $this->get('/spa-shell?path=/faq')->assertSee('<title>Old FAQ</title>', false);

        $cacheKey = app(SeoPageService::class)->shellCacheKey('/faq');
        $this->assertTrue(Cache::has($cacheKey));

        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        $this->postJson('/api/v1/admin/seo-pages/update', [
            'id' => $page->id,
            'meta_title' => 'New FAQ',
            'meta_description' => 'New desc',
            'meta_keywords' => null,
        ])->assertOk();

        $this->assertFalse(Cache::has($cacheKey));

        $this->get('/spa-shell?path=/faq')->assertSee('<title>New FAQ</title>', false);
    }

    public function test_spa_shell_injects_meta_for_marketplace_business(): void
    {
        $business = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
            'business_name' => 'Acme Plumbing',
            'business_description' => 'Reliable pipes and drains.',
        ]);

        $path = '/businesses/'.EncryptId::encrypt($business->id);
        $response = $this->get('/spa-shell?path='.$path);

        $response->assertOk();
        $response->assertSee('<title>Acme Plumbing | Gidira</title>', false);
        $response->assertSee('name="description" content="Reliable pipes and drains."', false);
        $response->assertSee('https://www.frontend.test'.$path, false);
    }

    public function test_spa_shell_skips_flagged_business(): void
    {
        $business = BusinessInfo::factory()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => true,
            'business_name' => 'Hidden Biz',
        ]);

        $path = '/businesses/'.EncryptId::encrypt($business->id);
        $response = $this->get('/spa-shell?path='.$path);

        $response->assertOk();
        $response->assertSee('<title>Gidira</title>', false);
        $response->assertDontSee('Hidden Biz', false);
        $response->assertDontSee('gidira-seo-start', false);
    }

    public function test_spa_shell_injects_meta_for_discoverable_catalog_item(): void
    {
        $premium = BusinessInfo::factory()->premiumActive()->create([
            'business_status' => BusinessStatus::Active,
            'is_flagged' => false,
        ]);

        $item = BusinessCatalogItem::query()->create([
            'business_info_id' => $premium->id,
            'type' => 'service',
            'name' => 'Emergency Fix',
            'description' => 'Same-day repairs.',
            'price_kobo' => 250000,
            'sort_order' => 0,
        ]);

        $path = '/catalog/items/'.$item->id;
        $response = $this->get('/spa-shell?path='.$path);

        $response->assertOk();
        $response->assertSee('<title>Emergency Fix | Gidira</title>', false);
        $response->assertSee('name="description" content="Same-day repairs."', false);
    }

    public function test_spa_shell_fetches_remote_template_when_path_unset(): void
    {
        config([
            'seo.spa_shell_index_path' => null,
            'seo.spa_shell_template_fetch' => true,
            'seo.spa_shell_template_url' => 'https://cdn.test/index.html',
        ]);
        Cache::flush();

        Http::fake([
            'https://cdn.test/index.html' => Http::response(
                '<!doctype html><html><head><title>Gidira</title><script src="/assets/app.js"></script></head><body><div id="root"></div></body></html>',
                200
            ),
        ]);

        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'About Via Fetch',
            'meta_description' => 'Fetched shell',
        ]);

        $response = $this->get('/spa-shell?path=/about');
        $response->assertOk();
        $response->assertSee('<title>About Via Fetch</title>', false);
        $response->assertSee('/assets/app.js', false);
        $response->assertSee('Fetched shell', false);

        Http::assertSentCount(1);
    }
}
