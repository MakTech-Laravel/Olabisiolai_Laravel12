<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\VerificationDocumentStatus;
use App\Enums\VerificationStatus;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerificationDocument;
use Database\Seeders\PricingPackageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminVerificationReverificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PricingPackageSeeder::class);
    }

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        return $admin;
    }

    /**
     * @return array{0: User, 1: BusinessInfo}
     */
    private function makeRevokedVerifiedBusiness(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Updated Shop Name',
            'verification_status' => VerificationStatus::None,
            'verified_at' => null,
            'is_flagged' => false,
        ]);

        Payment::factory()->completed()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 2500,
            'is_consumed' => true,
        ]);

        VerificationDocument::query()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'national_id',
            'title' => 'National ID',
            'file_path' => 'businesses/'.$business->id.'/verification/test.pdf',
            'file_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'status' => VerificationDocumentStatus::Approved,
        ]);

        return [$vendor, $business];
    }

    public function test_admin_business_update_revokes_verification_on_major_change(): void
    {
        $this->actingAsAdmin();

        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
            'business_name' => 'Original Name',
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/admin/business-info/update', [
            'business_info_id' => $business->id,
            'business_name' => 'Admin Updated Name',
        ]);

        $response->assertOk();

        $business->refresh();
        $this->assertSame(VerificationStatus::None, $business->verification_status);
        $this->assertNull($business->verified_at);
    }

    public function test_admin_can_grant_free_reverification(): void
    {
        $this->actingAsAdmin();
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $business = BusinessInfo::factory()->for($vendor)->create([
            'verification_status' => VerificationStatus::None,
            'is_flagged' => false,
        ]);

        Payment::factory()->completed()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 2500,
            'is_consumed' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/verifications/grant-reverification', [
            'business_info_id' => $business->id,
            'reason' => 'Profile updated by admin — re-verify at no cost.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payment.status', 'completed');
        $response->assertJsonPath('data.payment.is_consumed', false);
        $response->assertJsonPath('data.payment.amount', 0);
    }

    public function test_admin_can_grant_pending_verification_payment_manually(): void
    {
        $this->actingAsAdmin();
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $business = BusinessInfo::factory()->for($vendor)->create([
            'verification_status' => VerificationStatus::None,
            'is_flagged' => false,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 2500,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->postJson('/api/v1/admin/payments/'.$payment->id.'/grant', [
            'reason' => 'Paystack payment confirmed offline.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.payment.status', 'completed');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertFalse((bool) $payment->is_consumed);
    }

    public function test_admin_can_reapprove_verification_without_new_payment(): void
    {
        $this->actingAsAdmin();
        [, $business] = $this->makeRevokedVerifiedBusiness();

        $response = $this->postJson('/api/v1/admin/verifications/reapprove', [
            'business_info_id' => $business->id,
            'note' => 'Documents still valid after name correction.',
        ]);

        $response->assertOk();

        $business->refresh();
        $this->assertSame(VerificationStatus::Approved, $business->verification_status);
        $this->assertNotNull($business->verified_at);
    }

    public function test_admin_cannot_grant_reverification_when_documents_already_approved(): void
    {
        $this->actingAsAdmin();
        [, $business] = $this->makeRevokedVerifiedBusiness();

        $response = $this->postJson('/api/v1/admin/verifications/grant-reverification', [
            'business_info_id' => $business->id,
            'reason' => 'Trying again after waiver already exists.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Documents are already on file and approved. Use Re-approve now to restore the badge — do not grant another payment.',
        );
    }

    public function test_vendor_cannot_init_duplicate_verification_payment(): void
    {
        [, $business] = $this->makeRevokedVerifiedBusiness();
        $vendor = $business->user;
        $token = $vendor->createToken('vendor-test')->accessToken;

        Payment::factory()->completed()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 0,
            'is_consumed' => false,
            'metadata' => ['reverification_waiver' => true],
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/vendor/verification/payment/init', [
            'package_id' => 'individual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'You already have a free re-verification credit. Upload your documents — do not pay again.',
        ]);
    }

    public function test_revoked_business_with_documents_needs_admin_reapproval(): void
    {
        [, $business] = $this->makeRevokedVerifiedBusiness();
        $vendor = $business->user;
        $token = $vendor->createToken('vendor-test')->accessToken;

        $response = $this->withToken($token)->getJson('/api/v1/vendor/verification/status');

        $response->assertOk();
        $response->assertJsonPath('data.needs_admin_reapproval', true);
        $response->assertJsonPath('data.can_init_payment', false);
        $response->assertJsonPath('data.verification_status_label', 'Awaiting admin re-approval');
    }
}
