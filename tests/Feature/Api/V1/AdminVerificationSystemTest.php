<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\VerificationDocumentStatus;
use App\Models\Admin;
use App\Models\BusinessInfo;
use App\Models\Category;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerificationDocument;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminVerificationSystemTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'Testing Personal Access Client',
            config('auth.guards.api.provider'),
        );

        Storage::fake('public');

        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsAdmin(): Admin
    {
        $admin = Admin::query()->where('email', 'superadmin@dev.com')->firstOrFail();
        Passport::actingAs($admin, [], 'admin_api');

        return $admin;
    }

    private function makeVendorWithBusiness(string $verificationStatus = 'none'): array
    {
        $vendor = User::factory()->create(['role' => 'vendor', 'email_verified_at' => now()]);
        $category = Category::factory()->create();

        $isFlagged = $verificationStatus === 'flagged';
        $status = $isFlagged ? 'none' : $verificationStatus;

        $business = BusinessInfo::factory()->create([
            'user_id' => $vendor->id,
            'category_id' => $category->id,
            'verification_status' => $status,
            'is_flagged' => $isFlagged,
        ]);
        $token = $vendor->createToken('vendor-test')->accessToken;

        return [$vendor, $business, $token];
    }

    private function makeCompletedVerificationPayment(User $vendor, BusinessInfo $business): Payment
    {
        return Payment::factory()->completed()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'package_id' => 'individual',
            'amount' => 2500,
        ]);
    }

    public function test_vendor_can_list_verification_packages(): void
    {
        [,, $token] = $this->makeVendorWithBusiness();

        $response = $this->withToken($token)->getJson('/api/v1/vendor/verification/packages');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['currency', 'packages']]);
    }

    public function test_vendor_can_init_and_confirm_payment(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('none');

        $init = $this->withToken($token)->postJson('/api/v1/vendor/verification/payment/init', [
            'package_id' => 'individual',
        ]);

        $init->assertCreated();
        $paymentId = $init->json('data.payment.id');

        $confirm = $this->withToken($token)->postJson('/api/v1/vendor/verification/payment/confirm', [
            'payment_id' => $paymentId,
            'gateway_transaction_id' => 'FLW-TEST-12345',
            'gateway' => 'flutterwave',
        ]);

        $confirm->assertOk();
        $confirm->assertJsonPath('data.payment.status', 'completed');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => 'verification',
            'status' => 'completed',
        ]);
    }

    public function test_vendor_can_apply_after_completed_payment(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('none');
        $payment = $this->makeCompletedVerificationPayment($vendor, $business);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/apply', [
            'payment_id' => $payment->id,
            'documents' => [
                [
                    'document_type' => 'business_registration',
                    'title' => 'CAC Certificate',
                    'document' => UploadedFile::fake()->create('cac.pdf', 200, 'application/pdf'),
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('data.verification_status', 'pending');

        $this->assertDatabaseHas('verification_documents', [
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'business_registration',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'is_consumed' => true,
        ]);
    }

    public function test_vendor_cannot_apply_without_completed_payment(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('none');

        $payment = Payment::factory()->create([
            'user_id' => $vendor->id,
            'business_info_id' => $business->id,
            'purpose' => PaymentPurpose::Verification,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/apply', [
            'payment_id' => $payment->id,
            'documents' => [
                [
                    'document_type' => 'cac_document',
                    'title' => 'CAC',
                    'document' => UploadedFile::fake()->create('cac.pdf', 100, 'application/pdf'),
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
    }

    public function test_vendor_cannot_apply_again_while_pending(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('pending');
        $payment = $this->makeCompletedVerificationPayment($vendor, $business);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/apply', [
            'payment_id' => $payment->id,
            'documents' => [
                [
                    'document_type' => 'other',
                    'title' => 'Doc',
                    'document' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
    }

    public function test_vendor_can_reapply_after_flagged(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('flagged');
        $payment = $this->makeCompletedVerificationPayment($vendor, $business);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/apply', [
            'payment_id' => $payment->id,
            'documents' => [
                [
                    'document_type' => 'bank_transfer',
                    'title' => 'New docs',
                    'document' => UploadedFile::fake()->create('transfer.jpg', 150, 'image/jpeg'),
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_vendor_can_check_verification_status(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('pending');

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/vendor/verification/status');

        $response->assertOk();
        $response->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_vendor_status_includes_purchased_package_after_payment(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('none');
        $payment = $this->makeCompletedVerificationPayment($vendor, $business);

        $response = $this->withToken($token)->getJson('/api/v1/vendor/verification/status');

        $response->assertOk();
        $response->assertJsonPath('data.purchased_package.id', 'individual');
        $response->assertJsonPath('data.purchased_package.title', 'Individual');
        $response->assertJsonStructure(['data' => ['purchased_package' => ['usage_message', 'paid_at']]]);
        $response->assertJsonPath('data.awaiting_document_submission', true);
        $response->assertJsonPath('data.consumable_payment_id', $payment->id);
    }

    public function test_admin_can_list_verification_requests(): void
    {
        $this->actingAsAdmin();
        $this->makeVendorWithBusiness('pending');
        $this->makeVendorWithBusiness('approved');

        $response = $this->postJson('/api/v1/admin/verifications');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['filter', 'count', 'pagination', 'verifications']]);
    }

    public function test_admin_can_filter_by_pending_status(): void
    {
        $this->actingAsAdmin();
        $this->makeVendorWithBusiness('pending');
        $this->makeVendorWithBusiness('approved');
        $this->makeVendorWithBusiness('flagged');

        $response = $this->postJson('/api/v1/admin/verifications', [
            'verification_status' => 'pending',
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.count'));
    }

    public function test_admin_queue_filter_includes_pending_and_approved(): void
    {
        $this->actingAsAdmin();
        $this->makeVendorWithBusiness('pending');
        $this->makeVendorWithBusiness('approved');
        $this->makeVendorWithBusiness('flagged');

        $response = $this->postJson('/api/v1/admin/verifications', [
            'verification_status' => 'queue',
        ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.count'));
    }

    public function test_admin_can_approve_pending_verification(): void
    {
        $this->actingAsAdmin();
        [$vendor, $business] = $this->makeVendorWithBusiness('pending');

        $docIds = [];
        foreach (['business_registration', 'identity_proof', 'address_proof'] as $type) {
            $docIds[] = VerificationDocument::factory()->create([
                'business_info_id' => $business->id,
                'uploaded_by' => $vendor->id,
                'document_type' => $type,
                'status' => VerificationDocumentStatus::Pending,
            ])->id;
        }

        $response = $this->postJson('/api/v1/admin/verifications/approve', [
            'business_info_id' => $business->id,
            'note' => 'All documents verified.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verification.verification_status', 'approved');

        $this->assertDatabaseHas('business_info', [
            'id' => $business->id,
            'verification_status' => 'approved',
        ]);

        foreach ($docIds as $docId) {
            $this->assertDatabaseHas('verification_documents', [
                'id' => $docId,
                'status' => VerificationDocumentStatus::Approved->value,
            ]);
        }
    }

    public function test_admin_can_approve_pending_documents_when_business_already_approved(): void
    {
        $this->actingAsAdmin();
        [$vendor, $business] = $this->makeVendorWithBusiness('approved');

        foreach (['business_registration', 'address_proof'] as $type) {
            VerificationDocument::factory()->create([
                'business_info_id' => $business->id,
                'uploaded_by' => $vendor->id,
                'document_type' => $type,
                'status' => VerificationDocumentStatus::Approved,
            ]);
        }

        $docId = VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'identity_proof',
            'status' => VerificationDocumentStatus::Pending,
        ])->id;

        $response = $this->postJson('/api/v1/admin/verifications/approve', [
            'business_info_id' => $business->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verification.verification_status', 'approved');

        $this->assertDatabaseHas('verification_documents', [
            'id' => $docId,
            'status' => VerificationDocumentStatus::Approved->value,
        ]);
    }

    public function test_admin_cannot_approve_verification_with_rejected_documents(): void
    {
        $this->actingAsAdmin();
        [$vendor, $business] = $this->makeVendorWithBusiness('pending');

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'business_registration',
            'status' => VerificationDocumentStatus::Rejected,
            'rejection_reason' => 'It is a fake document.',
        ]);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'identity_proof',
            'status' => VerificationDocumentStatus::Rejected,
            'rejection_reason' => 'Please submit again.',
        ]);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'address_proof',
            'status' => VerificationDocumentStatus::Approved,
        ]);

        $response = $this->postJson('/api/v1/admin/verifications/approve', [
            'business_info_id' => $business->id,
        ]);

        $response->assertStatus(422);
        $message = (string) $response->json('message');
        $this->assertStringContainsString('Cannot approve verification while rejected documents remain', $message);
        $this->assertStringContainsString('business registration', $message);
        $this->assertStringContainsString('identity proof', $message);

        $this->assertDatabaseHas('business_info', [
            'id' => $business->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_admin_can_flag_pending_verification(): void
    {
        $this->actingAsAdmin();
        [, $business] = $this->makeVendorWithBusiness('pending');

        $response = $this->postJson('/api/v1/admin/verifications/flag', [
            'business_info_id' => $business->id,
            'reason' => 'Documents are unclear and need to be resubmitted.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verification.verification_status', 'none');
        $response->assertJsonPath('data.verification.is_flagged', true);

        $this->assertDatabaseHas('business_info', [
            'id' => $business->id,
            'verification_status' => 'none',
            'is_flagged' => true,
        ]);
    }

    public function test_admin_flag_requires_reason(): void
    {
        $this->actingAsAdmin();
        [, $business] = $this->makeVendorWithBusiness('pending');

        $response = $this->postJson('/api/v1/admin/verifications/flag', [
            'business_info_id' => $business->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_vendor_can_reupload_document_while_pending(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('pending');

        $rejected = VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'business_registration',
            'title' => 'Business Registration',
            'status' => VerificationDocumentStatus::Rejected,
            'rejection_reason' => 'not valid',
        ]);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/documents/upload', [
            'document_type' => 'business_registration',
            'title' => 'Business Registration',
            'parent_document_id' => $rejected->id,
            'document' => UploadedFile::fake()->create('replacement.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('data.document.status', 'pending');

        $this->assertDatabaseHas('verification_documents', [
            'business_info_id' => $business->id,
            'parent_document_id' => $rejected->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(2, VerificationDocument::query()->where('business_info_id', $business->id)->count());
    }

    public function test_vendor_can_reupload_rejected_document_after_verification_reset(): void
    {
        [$vendor, $business, $token] = $this->makeVendorWithBusiness('none');
        $this->makeCompletedVerificationPayment($vendor, $business);

        $rejected = VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'business_registration',
            'title' => 'Business Registration',
            'status' => VerificationDocumentStatus::Rejected,
            'rejection_reason' => 'not valid',
        ]);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'identity_proof',
            'status' => VerificationDocumentStatus::Approved,
        ]);

        $response = $this->withToken($token)->post('/api/v1/vendor/verification/documents/upload', [
            'document_type' => 'business_registration',
            'title' => 'Business Registration',
            'parent_document_id' => $rejected->id,
            'document' => UploadedFile::fake()->create('replacement.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('data.document.status', 'pending');

        $this->assertDatabaseHas('business_info', [
            'id' => $business->id,
            'verification_status' => 'pending',
        ]);
    }

    public function test_admin_can_review_documents_when_verification_status_is_none(): void
    {
        $this->actingAsAdmin();
        [$vendor, $business] = $this->makeVendorWithBusiness('none');
        $this->makeCompletedVerificationPayment($vendor, $business);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'business_registration',
            'status' => VerificationDocumentStatus::Rejected,
        ]);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'identity_proof',
            'status' => VerificationDocumentStatus::Approved,
        ]);

        VerificationDocument::factory()->create([
            'business_info_id' => $business->id,
            'uploaded_by' => $vendor->id,
            'document_type' => 'address_proof',
            'status' => VerificationDocumentStatus::Approved,
        ]);

        $view = $this->postJson('/api/v1/admin/verifications/view', [
            'business_info_id' => $business->id,
        ]);

        $view->assertOk();
        $view->assertJsonPath('data.verification.has_open_document_review', true);
        $view->assertJsonPath('data.verification.can_approve_all', false);

        $approve = $this->postJson('/api/v1/admin/verifications/approve', [
            'business_info_id' => $business->id,
        ]);

        $approve->assertStatus(422);
    }

    public function test_admin_can_delete_verification_and_vendor_becomes_unverified(): void
    {
        $this->actingAsAdmin();
        [$vendor, $business] = $this->makeVendorWithBusiness('approved');

        $business->update([
            'verified_at' => now(),
            'verified_by' => $vendor->id,
        ]);

        $response = $this->postJson('/api/v1/admin/verifications/delete', [
            'business_info_id' => $business->id,
            'reason' => 'Documents no longer valid.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.verification.verification_status', 'none');
        $response->assertJsonPath('data.verification.is_approved', false);

        $this->assertDatabaseHas('business_info', [
            'id' => $business->id,
            'verification_status' => 'none',
            'is_flagged' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function test_non_admin_cannot_access_admin_verification_endpoints(): void
    {
        [, $business, $vendorToken] = $this->makeVendorWithBusiness('pending');

        $this->withToken($vendorToken)->postJson('/api/v1/admin/verifications')->assertUnauthorized();
        $this->withToken($vendorToken)->postJson('/api/v1/admin/verifications/approve', ['business_info_id' => $business->id])->assertUnauthorized();
        $this->withToken($vendorToken)->postJson('/api/v1/admin/verifications/flag', ['business_info_id' => $business->id, 'reason' => 'test reason here'])->assertUnauthorized();
        $this->withToken($vendorToken)->postJson('/api/v1/admin/verifications/delete', ['business_info_id' => $business->id])->assertUnauthorized();
    }
}
