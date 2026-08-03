<?php

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\SeoPage;
use App\Services\SitemapService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SeoPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SeoPageApiTest extends TestCase
{
    use RefreshDatabase;

    private string $frontendBase = 'https://www.frontend.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => $this->frontendBase,
            'app.url' => 'http://api.test',
        ]);

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
        File::deleteDirectory(storage_path('app/sitemap'));
    }

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        return $admin;
    }

    public function test_admin_can_list_and_update_seo_meta(): void
    {
        $this->actingAsAdmin();
        $this->seed(SeoPageSeeder::class);

        $list = $this->postJson('/api/v1/admin/seo-pages', ['per_page' => 50]);
        $list->assertOk();
        $list->assertJsonPath('success', true);
        $this->assertNotEmpty($list->json('data.pages'));

        $about = SeoPage::query()->where('path', '/about')->firstOrFail();

        $update = $this->postJson('/api/v1/admin/seo-pages/update', [
            'id' => $about->id,
            'meta_title' => 'About Gidira',
            'meta_description' => 'Learn about Gidira marketplace.',
            'meta_keywords' => 'gidira, about',
            'canonical_url' => 'https://www.frontend.test/about-us',
            'noindex' => true,
            'og_image' => 'https://cdn.test/about.jpg',
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.page.meta_title', 'About Gidira');
        $update->assertJsonPath('data.page.noindex', true);
        $update->assertJsonPath('data.page.canonical_url', 'https://www.frontend.test/about-us');
        $update->assertJsonPath('data.page.og_image', 'https://cdn.test/about.jpg');
        $this->assertDatabaseHas('seo_pages', [
            'id' => $about->id,
            'meta_title' => 'About Gidira',
            'noindex' => 1,
        ]);
    }

    public function test_guest_cannot_access_admin_seo_endpoints(): void
    {
        $this->postJson('/api/v1/admin/seo-pages')->assertUnauthorized();
        $this->postJson('/api/v1/admin/seo-pages/generate-sitemap')->assertUnauthorized();
    }

    public function test_admin_generate_sitemap_writes_file(): void
    {
        $this->actingAsAdmin();
        $this->seed(SeoPageSeeder::class);

        $response = $this->postJson('/api/v1/admin/seo-pages/generate-sitemap');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThan(0, (int) $response->json('data.urls'));
        $this->assertFileExists(app(SitemapService::class)->path());
    }

    public function test_generate_sitemap_returns_429_when_lock_held(): void
    {
        $this->actingAsAdmin();

        $lock = Cache::lock('sitemap:generate', 30);
        $this->assertTrue($lock->get());

        try {
            $response = $this->postJson('/api/v1/admin/seo-pages/generate-sitemap');
            $response->assertStatus(429);
            $response->assertJsonPath('success', false);
        } finally {
            $lock->release();
        }
    }

    public function test_public_by_path_returns_meta_and_404_for_unknown(): void
    {
        SeoPage::factory()->create([
            'path' => '/about',
            'page_name' => 'About',
            'meta_title' => 'About title',
            'meta_description' => 'About desc',
            'meta_keywords' => 'a,b',
        ]);

        $ok = $this->getJson('/api/v1/seo-pages/by-path?path=/about');
        $ok->assertOk();
        $ok->assertJsonPath('success', true);
        $ok->assertJsonPath('data.meta_title', 'About title');

        $missing = $this->getJson('/api/v1/seo-pages/by-path?path=/does-not-exist');
        $missing->assertNotFound();
        $missing->assertJsonPath('success', false);
        $missing->assertJsonStructure(['success', 'message']);
    }

    public function test_seo_page_updated_at_used_as_static_lastmod_after_generate(): void
    {
        $this->actingAsAdmin();
        $this->seed(SeoPageSeeder::class);

        $about = SeoPage::query()->where('path', '/about')->firstOrFail();
        $stamp = now()->subDays(5)->startOfSecond();
        $about->forceFill(['updated_at' => $stamp])->save();

        $this->postJson('/api/v1/admin/seo-pages/generate-sitemap')->assertOk();

        $xml = (string) file_get_contents(app(SitemapService::class)->path());
        $loc = '<loc>'.$this->frontendBase.'/about</loc>';
        $pos = strpos($xml, $loc);
        $this->assertNotFalse($pos);
        $snippet = substr($xml, $pos, 400);
        $this->assertStringContainsString('<lastmod>'.$stamp->toAtomString().'</lastmod>', $snippet);
    }
}
