<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessInfoResource;
use App\Models\BusinessInfo;
use App\Models\User;
use App\Http\Resources\MessageResource;
use App\Services\AdminMessagingService;
use App\Services\BusinessInfoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BusinessInfoController extends Controller
{
    public function __construct(
        private readonly BusinessInfoService $businessInfoService,
        private readonly AdminMessagingService $adminMessaging,
    ) {}

    public function allBusinessInfo(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'verification_status' => ['nullable', 'string', Rule::in(VerificationStatus::values())],
                'business_status' => ['nullable', 'string', Rule::in(BusinessStatus::values())],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'boost_status' => ['nullable', 'string', Rule::in(['active', 'none'])],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
            $search = $search === '' ? null : $search;

            $boostStatus = $validated['boost_status'] ?? null;

            $businessProfiles = $this->businessInfoService->paginateForAdmin(
                $search,
                $validated['verification_status'] ?? null,
                (int) ($validated['per_page'] ?? 10),
                $validated['business_status'] ?? null,
                isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                isset($validated['page']) ? (int) $validated['page'] : null,
                $boostStatus,
            );

            $summary = $this->businessInfoService->getAdminBusinessListSummary(
                $search,
                $validated['verification_status'] ?? null,
                $validated['business_status'] ?? null,
                isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                $boostStatus,
            );

            $items = $businessProfiles->items();
            foreach ($items as $business) {
                if (! isset($business->is_flagged)) {
                    $business->is_flagged = false;
                }
            }

            return sendResponse(true, $businessProfiles->total() === 0 ? 'No business profiles found.' : 'Business profiles retrieved successfully.', [
                'filter' => [
                    'search' => $search,
                    'verification_status' => $validated['verification_status'] ?? 'all',
                    'business_status' => $validated['business_status'] ?? 'all',
                    'category_id' => $validated['category_id'] ?? null,
                    'boost_status' => $boostStatus ?? 'all',
                ],
                'filter_options' => $this->businessInfoService->getAdminBusinessFilterOptions(),
                'summary' => $summary,
                'count' => $businessProfiles->total(),
                'pagination' => [
                    'current_page' => $businessProfiles->currentPage(),
                    'per_page' => $businessProfiles->perPage(),
                    'last_page' => max(1, $businessProfiles->lastPage()),
                    'total' => $businessProfiles->total(),
                ],
                'business_profiles' => BusinessInfoResource::collection($businessProfiles),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewBusinessInfo(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
            ]);

            $businessInfo = $this->businessInfoService->getBusinessInfoByIdForAdmin((int) $validated['business_info_id']);

            return sendResponse(true, 'Business profile retrieved successfully.', [
                'business' => new BusinessInfoResource($businessInfo),
                'messages' => AdminVendorMessageResource::collection($businessInfo->messages),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changeBusinessStatus(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'status' => ['required', 'string', Rule::in(BusinessStatus::values())],
            ]);

            $businessInfo = $this->businessInfoService->getBusinessInfoByIdForAdmin((int) $validated['business_info_id']);
            $updatedBusiness = $this->businessInfoService->changeBusinessStatus($businessInfo, BusinessStatus::from((string) $validated['status']));

            return sendResponse(true, 'Business status updated successfully.', [
                'business' => new BusinessInfoResource($updatedBusiness),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendMessage(Request $request)
    {
        try {
            $admin = adminAuthCheck($request);

            if (! $admin) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'message' => ['nullable', 'string', 'max:5000'],
            ]);

            $businessInfo = $this->businessInfoService->getBusinessInfoByIdForAdmin((int) $validated['business_info_id']);
            $body = trim((string) ($validated['message'] ?? ''));

            $conversation = $this->adminMessaging->startOrGetVendorConversationForBusiness($admin, $businessInfo);

            $messageModel = null;
            if ($body !== '') {
                $messageModel = $this->adminMessaging->sendToVendor($admin, $conversation, $body);
                $messageModel->loadMissing('conversation');
            }

            $businessInfo->loadMissing('user');

            return sendResponse(true, 'Conversation ready.', [
                'conversation_uuid' => $conversation->uuid,
                'vendor_user_uuid' => $businessInfo->user?->uuid,
                'message' => $messageModel !== null ? new MessageResource($messageModel) : null,
            ], $messageModel !== null ? Response::HTTP_CREATED : Response::HTTP_OK);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new business profile (Admin only).
     */
    public function create(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'location_id' => ['required', 'integer', 'exists:locations,id'],
                'category_id' => ['required', 'integer', 'exists:categories,id'],
                'business_name' => ['required', 'string', 'max:255'],
                'business_description' => ['required', 'string', 'max:10000'],
                'services_offered' => ['required', 'array', 'min:1'],
                'services_offered.*' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:30'],
                'whatsapp' => ['nullable', 'string', 'max:30'],
                'website' => ['nullable', 'string', 'max:2048', 'url'],
                'logo_path' => ['nullable', 'string', 'max:500'],
                'cover_photo_paths' => ['nullable', 'array', 'max:5'],
                'cover_photo_paths.*' => ['nullable', 'string', 'max:500'],
                'verification_status' => ['nullable', 'string', 'in:none,pending,verified,approved'],
                'business_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
                'is_flagged' => ['nullable', 'boolean'],
                'verification_note' => ['nullable', 'string', 'max:5000'],
            ]);

            // Check if user already has a business profile
            if ($this->businessInfoService->userAlreadyHasProfile(User::find($validated['user_id']))) {
                return sendResponse(false, 'User already has a business profile.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $business = BusinessInfo::create([
                'user_id' => $validated['user_id'],
                'location_id' => $validated['location_id'],
                'category_id' => $validated['category_id'],
                'business_name' => $validated['business_name'],
                'business_description' => $validated['business_description'],
                'services_offered' => $validated['services_offered'],
                'phone' => $validated['phone'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'website' => $validated['website'] ?? null,
                'logo_path' => $validated['logo_path'] ?? null,
                'cover_photo_paths' => $validated['cover_photo_paths'] ?? [],
                'verification_status' => $validated['verification_status'] ?? VerificationStatus::None->value,
                'business_status' => $validated['business_status'] ?? BusinessStatus::Active->value,
                'is_flagged' => $validated['is_flagged'] ?? false,
                'verification_note' => $validated['verification_note'] ?? null,
                'verified_by' => adminAuthCheck($request)->id,
                'verified_at' => ($validated['verification_status'] ?? 'none') === 'verified' ? now() : null,
            ]);

            $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name', 'user:id,name,email,phone']);

            return sendResponse(true, 'Business profile created successfully.', [
                'business' => new BusinessInfoResource($business),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a business profile (Admin only).
     */
    public function update(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
                'location_id' => ['nullable', 'integer', 'exists:locations,id'],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'business_name' => ['nullable', 'string', 'max:255'],
                'business_description' => ['nullable', 'string', 'max:10000'],
                'services_offered' => ['nullable', 'array', 'min:1'],
                'services_offered.*' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'whatsapp' => ['nullable', 'string', 'max:30'],
                'website' => ['nullable', 'string', 'max:2048', 'url'],
                'logo_path' => ['nullable', 'string', 'max:500'],
                'cover_photo_paths' => ['nullable', 'array', 'max:5'],
                'cover_photo_paths.*' => ['nullable', 'string', 'max:500'],
                'verification_status' => ['nullable', 'string', 'in:none,pending,verified,approved'],
                'business_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
                'is_flagged' => ['nullable', 'boolean'],
                'verification_note' => ['nullable', 'string', 'max:5000'],
            ]);

            $business = BusinessInfo::findOrFail($validated['business_info_id']);

            $updateData = array_filter($validated, function ($value, $key) {
                return ! in_array($key, ['business_info_id']) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            // Handle verification status changes
            if (isset($updateData['verification_status'])) {
                if ($updateData['verification_status'] === 'verified') {
                    $updateData['verified_by'] = adminAuthCheck($request)->id;
                    $updateData['verified_at'] = now();
                } else {
                    $updateData['verified_by'] = null;
                    $updateData['verified_at'] = null;
                }
            }

            $business->update($updateData);

            $business->load(['category:id,name,subcategories', 'location:id,lga_name,state_name,city_name,country_name', 'user:id,name,email,phone', 'verifiedBy:id,name,email']);

            return sendResponse(true, 'Business profile updated successfully.', [
                'business' => new BusinessInfoResource($business),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a business profile (Admin only).
     */
    public function delete(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_info_id' => ['required', 'integer', 'exists:business_info,id'],
            ]);

            $business = BusinessInfo::findOrFail($validated['business_info_id']);

            // Store business info for response before deletion
            $businessData = new BusinessInfoResource($business);

            $business->delete();

            return sendResponse(true, 'Business profile deleted successfully.', [
                'business' => $businessData,
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update business profiles (Admin only).
     */
    public function bulkUpdate(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'business_ids' => ['required', 'array', 'min:1'],
                'business_ids.*' => ['required', 'integer', 'exists:business_info,id'],
                'verification_status' => ['nullable', 'string', 'in:none,pending,verified,approved'],
                'business_status' => ['nullable', 'string', 'in:active,inactive,suspended'],
                'is_flagged' => ['nullable', 'boolean'],
                'verification_note' => ['nullable', 'string', 'max:5000'],
            ]);

            $updateData = array_filter($validated, function ($value, $key) {
                return ! in_array($key, ['business_ids']) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($updateData)) {
                return sendResponse(false, 'No valid fields to update.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Handle verification status changes
            if (isset($updateData['verification_status'])) {
                if ($updateData['verification_status'] === 'verified') {
                    $updateData['verified_by'] = adminAuthCheck($request)->id;
                    $updateData['verified_at'] = now();
                } else {
                    $updateData['verified_by'] = null;
                    $updateData['verified_at'] = null;
                }
            }

            $updatedCount = BusinessInfo::whereIn('id', $validated['business_ids'])->update($updateData);

            return sendResponse(true, "Successfully updated {$updatedCount} business profiles.", [
                'updated_count' => $updatedCount,
                'business_ids' => $validated['business_ids'],
                'updated_fields' => array_keys($updateData),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get business statistics (Admin only).
     */
    public function statistics(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $stats = [
                'total_businesses' => BusinessInfo::count(),
                'by_verification_status' => BusinessInfo::selectRaw('verification_status, COUNT(*) as count')
                    ->groupBy('verification_status')
                    ->pluck('count', 'verification_status')
                    ->toArray(),
                'by_business_status' => BusinessInfo::selectRaw('business_status, COUNT(*) as count')
                    ->groupBy('business_status')
                    ->pluck('count', 'business_status')
                    ->toArray(),
                'flagged_businesses' => Schema::hasColumn('business_info', 'is_flagged')
                    ? BusinessInfo::where('is_flagged', true)->count()
                    : 0,
                'recent_businesses' => BusinessInfo::latest()->take(5)->get(['id', 'business_name', 'created_at']),
            ];

            return sendResponse(true, 'Business statistics retrieved successfully.', $stats);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
