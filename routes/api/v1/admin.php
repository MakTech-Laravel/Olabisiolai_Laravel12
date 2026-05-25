<?php

use App\Http\Controllers\Api\V1\Admin\AdminAccountController;
use App\Http\Controllers\Api\V1\Admin\AdminBoostRequestController;
use App\Http\Controllers\Api\V1\Admin\AdminMessagingController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentsController;
use App\Http\Controllers\Api\V1\Admin\AdminPricingController;
use App\Http\Controllers\Api\V1\Admin\BusinessInfoController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CmsPageController;
use App\Http\Controllers\Api\V1\Admin\ContactMessageController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\LocationController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\ReviewController;
use App\Http\Controllers\Api\V1\Admin\ReviewReportController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes (role: admin)
| Middleware: auth:api, verified, role:admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sidebar-counts', [DashboardController::class, 'sidebarCounts'])->name('sidebar-counts');

    Route::prefix('users')->name('users.')->group(function () {
        Route::post('/summary', [UserController::class, 'userManagementSummary'])->name('summary');
        Route::post('/', [UserController::class, 'allUsers'])->name('index');
        Route::post('/view', [UserController::class, 'viewUser'])->name('view');
        Route::post('/status-change', [UserController::class, 'changeUserStatus'])->name('status-change');
        Route::post('/delete', [UserController::class, 'deleteUser'])->name('delete');
    });

    Route::prefix('cms')->name('cms.')->group(function () {
        Route::post('/', [CmsPageController::class, 'index'])->name('index');
        Route::post('/view', [CmsPageController::class, 'view'])->name('view');
        Route::post('/upsert', [CmsPageController::class, 'upsert'])->name('upsert');
        Route::post('/upload-image', [CmsPageController::class, 'uploadImage'])->name('upload-image');
    });

    Route::prefix('categories')->name('categories.')->group(function () {
        Route::post('/', [CategoryController::class, 'allCategories'])->name('index');
        Route::post('/create', [CategoryController::class, 'createCategory'])->name('create');
        Route::post('/view', [CategoryController::class, 'viewCategory'])->name('view');
        Route::post('/update', [CategoryController::class, 'updateCategory'])->name('update');
        Route::post('/delete', [CategoryController::class, 'deleteCategory'])->name('delete');
    });

    Route::prefix('business-info')->name('business-info.')->group(function () {
        Route::post('/', [BusinessInfoController::class, 'allBusinessInfo'])->name('index');
        Route::post('/view', [BusinessInfoController::class, 'viewBusinessInfo'])->name('view');
        Route::post('/create', [BusinessInfoController::class, 'create'])->name('create');
        Route::post('/update', [BusinessInfoController::class, 'update'])->name('update');
        Route::post('/delete', [BusinessInfoController::class, 'delete'])->name('delete');
        Route::post('/bulk-update', [BusinessInfoController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/status-change', [BusinessInfoController::class, 'changeBusinessStatus'])->name('status-change');
        Route::post('/message', [BusinessInfoController::class, 'sendMessage'])->name('message');
        Route::post('/statistics', [BusinessInfoController::class, 'statistics'])->name('statistics');
    });

    Route::prefix('messaging')->name('messaging.')->group(function (): void {
        Route::get('/identity', [AdminMessagingController::class, 'identity'])->name('identity');
        Route::get('/conversations/search', [AdminMessagingController::class, 'searchConversations'])->name('conversations.search');
        Route::get('/conversations', [AdminMessagingController::class, 'indexConversations'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [AdminMessagingController::class, 'showConversation'])->name('conversations.show');
        Route::get('/conversations/{conversation}/messages', [AdminMessagingController::class, 'indexMessages'])
            ->name('conversations.messages.index');
        Route::post('/conversations/{conversation}/messages', [AdminMessagingController::class, 'storeMessage'])
            ->middleware(['messaging.throttle', 'throttle:60,1'])
            ->name('conversations.messages.store');
        Route::patch('/messages/{message}', [AdminMessagingController::class, 'updateMessage'])
            ->middleware(['throttle:60,1'])
            ->name('messages.update');
        Route::delete('/messages/{message}', [AdminMessagingController::class, 'destroyMessage'])
            ->middleware(['throttle:60,1'])
            ->name('messages.destroy');
        Route::post('/messages/{message}/read', [AdminMessagingController::class, 'markMessageRead'])
            ->middleware(['throttle:60,1'])
            ->name('messages.read');
        Route::post('/conversations/{conversation}/typing', [AdminMessagingController::class, 'typing'])
            ->middleware('messaging.presence')
            ->name('conversations.typing');
        Route::post('/attachments', [AdminMessagingController::class, 'storeAttachment'])
            ->middleware(['throttle:60,1'])
            ->name('attachments.store');
        Route::delete('/attachments/{attachment}', [AdminMessagingController::class, 'destroyAttachment'])
            ->middleware(['throttle:60,1'])
            ->name('attachments.destroy');
    });

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [AdminPaymentsController::class, 'index'])->name('index');
        Route::get('/analytics', [AdminPaymentsController::class, 'analytics'])->name('analytics');
        Route::get('/export', [AdminPaymentsController::class, 'export'])->name('export');
        Route::get('/{payment}', [AdminPaymentsController::class, 'show'])->name('show');
    });

    Route::prefix('pricing')->name('pricing.')->group(function () {
        Route::post('/', [AdminPricingController::class, 'index'])->name('index');
        Route::post('/verification/update', [AdminPricingController::class, 'updateVerification'])->name('verification.update');
        Route::post('/subscription/update', [AdminPricingController::class, 'updateSubscription'])->name('subscription.update');
    });

    Route::prefix('verifications')->name('verifications.')->group(function () {
        Route::post('/', [VerificationController::class, 'index'])->name('index');
        Route::post('/view', [VerificationController::class, 'view'])->name('view');
        Route::post('/approve', [VerificationController::class, 'approve'])->name('approve');
        Route::post('/flag', [VerificationController::class, 'flag'])->name('flag');
        Route::post('/delete', [VerificationController::class, 'destroy'])->name('delete');
        Route::post('/note', [VerificationController::class, 'addNote'])->name('note');
        Route::post('/documents/review', [VerificationController::class, 'reviewDocument'])->name('documents.review');
    });

    Route::prefix('boost-requests')->name('boost-requests.')->group(function () {
        Route::post('/', [AdminBoostRequestController::class, 'index'])->name('index');
        Route::post('/waiting-list', [AdminBoostRequestController::class, 'waitingList'])->name('waiting-list');
        Route::post('/show', [AdminBoostRequestController::class, 'show'])->name('show');
        Route::post('/campaigns', [AdminBoostRequestController::class, 'campaigns'])->name('campaigns');
        Route::post('/approve', [AdminBoostRequestController::class, 'approve'])->name('approve');
        Route::post('/reject', [AdminBoostRequestController::class, 'reject'])->name('reject');
        Route::post('/flag', [AdminBoostRequestController::class, 'flag'])->name('flag');
    });

    Route::prefix('locations')->name('locations.')->group(function () {
        Route::post('/', [LocationController::class, 'index'])->name('index');
        Route::post('/store', [LocationController::class, 'store'])->name('store');
        Route::post('/update', [LocationController::class, 'update'])->name('update');
        Route::post('/delete', [LocationController::class, 'destroy'])->name('delete');
        Route::post('/status-change', [LocationController::class, 'changeStatus'])->name('status-change');
        Route::post('/boost-active', [LocationController::class, 'toggleBoostActive'])->name('boost-active');
        Route::post('/vendors', [LocationController::class, 'locationVendors'])->name('vendors');
        Route::post('/vendors/sync', [LocationController::class, 'syncLocationVendors'])->name('vendors.sync');
    });

    Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::post('/', [ContactMessageController::class, 'index'])->name('index');
        Route::post('/{contactMessage}/view', [ContactMessageController::class, 'show'])->name('view');
        Route::post('/{contactMessage}/update', [ContactMessageController::class, 'update'])->name('update');
        Route::post('/{contactMessage}/delete', [ContactMessageController::class, 'destroy'])->name('delete');
    });

    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::post('/', [ReviewController::class, 'index'])->name('index');
        Route::post('/{review}/view', [ReviewController::class, 'show'])->name('view');
        Route::post('/{review}/update', [ReviewController::class, 'update'])->name('update');
        Route::post('/{review}/delete', [ReviewController::class, 'destroy'])->name('delete');
        Route::post('/bulk-approve', [ReviewController::class, 'bulkApprove'])->name('bulk-approve');
        Route::post('/bulk-flag', [ReviewController::class, 'bulkFlag'])->name('bulk-flag');
        Route::post('/statistics', [ReviewController::class, 'statistics'])->name('statistics');
    });

    Route::prefix('review-reports')->name('review-reports.')->group(function () {
        Route::get('/', [ReviewReportController::class, 'index'])->name('index');
        Route::get('/reasons', [ReviewReportController::class, 'reasons'])->name('reasons');
        Route::get('/{reviewReport}', [ReviewReportController::class, 'show'])->name('show');
        Route::post('/{reviewReport}/dismiss', [ReviewReportController::class, 'dismiss'])->name('dismiss');
        Route::post('/{reviewReport}/resolve', [ReviewReportController::class, 'resolve'])->name('resolve');
    });

    Route::middleware(['role_or_permission:super-admin|create admins'])
        ->post('admins', [AdminAccountController::class, 'store']);

    Route::middleware(['role_or_permission:super-admin|view admins'])
        ->get('admins', [AdminAccountController::class, 'index']);

    Route::middleware(['role_or_permission:super-admin|view admins'])
        ->get('admins/{id}', [AdminAccountController::class, 'show']);

    Route::middleware(['role_or_permission:super-admin|view admins'])
        ->get('admins/{id}/rbac-check', [AdminAccountController::class, 'rbacCheck']);

    Route::middleware(['role_or_permission:super-admin|edit admins'])
        ->put('admins/{id}/role-permissions', [AdminAccountController::class, 'assignRolePermissions']);

    Route::middleware(['role_or_permission:super-admin|change admin status'])
        ->put('admins/{id}/status', [AdminAccountController::class, 'updateStatus']);

    Route::middleware(['role_or_permission:super-admin|delete admins'])
        ->delete('admins/{id}', [AdminAccountController::class, 'destroy']);

    Route::middleware(['role_or_permission:super-admin|view roles'])->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
        Route::get('permissions', [PermissionController::class, 'index']);
    });

    Route::middleware(['role_or_permission:super-admin|create roles'])
        ->post('roles', [RoleController::class, 'store']);

    Route::middleware(['role_or_permission:super-admin|edit roles'])
        ->put('roles/{id}', [RoleController::class, 'update']);

    Route::middleware(['role_or_permission:super-admin|delete roles'])
        ->delete('roles/{id}', [RoleController::class, 'destroy']);
});
