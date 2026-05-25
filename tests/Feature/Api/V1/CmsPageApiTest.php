<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CmsPageType;
use App\Models\Admin;
use App\Models\CmsPage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CmsPageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        return $admin;
    }

    public function test_admin_can_create_cms_page_by_type(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/cms/upsert', [
            'type' => CmsPageType::PrivacyPolicy->value,
            'title' => 'Privacy Policy',
            'description' => '<p>Your privacy matters.</p>',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_created', true);
        $response->assertJsonPath('data.page.type', CmsPageType::PrivacyPolicy->value);
        $this->assertDatabaseCount('cms_pages', 1);
    }

    public function test_admin_upsert_updates_existing_page_for_same_type(): void
    {
        $this->actingAsAdmin();

        CmsPage::factory()->type(CmsPageType::AboutUs)->create([
            'title' => 'Old Title',
            'description' => '<p>Old content</p>',
        ]);

        $response = $this->postJson('/api/v1/admin/cms/upsert', [
            'type' => CmsPageType::AboutUs->value,
            'title' => 'About Us',
            'description' => '<p>Updated content</p>',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_created', false);
        $response->assertJsonPath('data.page.title', 'About Us');
        $this->assertDatabaseCount('cms_pages', 1);
        $this->assertDatabaseHas('cms_pages', [
            'type' => CmsPageType::AboutUs->value,
            'title' => 'About Us',
        ]);
    }

    public function test_public_can_fetch_cms_page_by_type(): void
    {
        CmsPage::factory()->type(CmsPageType::TermsAndConditions)->create([
            'title' => 'Terms and Conditions',
            'description' => '<p>Terms content</p>',
        ]);

        $response = $this->getJson('/api/v1/terms');

        $response->assertOk();
        $response->assertJsonPath('data.page.title', 'Terms and Conditions');
    }

    public function test_upsert_rejects_invalid_type(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/cms/upsert', [
            'type' => 'faq',
            'title' => 'FAQ',
            'description' => '<p>FAQ</p>',
        ]);

        $response->assertUnprocessable();
    }
}
