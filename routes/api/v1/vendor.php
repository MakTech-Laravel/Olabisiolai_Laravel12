<?php

use App\Http\Controllers\Api\V1\BusinessInfoController;
use App\Http\Controllers\Api\V1\Vendor\VendorAdminChatController;
use App\Http\Controllers\Api\V1\Vendor\VendorAnalyticsController;
use App\Http\Controllers\Api\V1\Vendor\VendorDashboardController;
use App\Http\Controllers\Api\V1\Vendor\VendorBoostController;
use App\Http\Controllers\Api\V1\Vendor\VendorOnboardingController;
use App\Http\Controllers\Api\V1\Vendor\VendorPaymentMethodsController;
use App\Http\Controllers\Api\V1\Vendor\VendorPaymentsController;
use App\Http\Controllers\Api\V1\Vendor\VendorReviewController;
use App\Http\Controllers\Api\V1\Vendor\VendorSettingsController;
use App\Http\Controllers\Api\V1\Vendor\VendorSubscriptionController;
use App\Http\Controllers\Api\V1\Vendor\VendorTwoFactorController;
use App\Http\Controllers\Api\V1\Vendor\VendorVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor API Routes
| Middleware: auth:api, verified, role:vendor
|--------------------------------------------------------------------------
*/

Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/settings', [VendorSettingsController::class, 'show'])->name('settings.show');
    Route::patch('/settings', [VendorSettingsController::class, 'update'])->name('settings.update');
    Route::post('/password', [VendorSettingsController::class, 'changePassword'])->name('password.change');

    Route::prefix('two-factor')->name('two-factor.')->group(function () {
        Route::get('/', [VendorTwoFactorController::class, 'status'])->name('status');
        Route::post('/enable', [VendorTwoFactorController::class, 'enable'])->name('enable');
        Route::post('/confirm', [VendorTwoFactorController::class, 'confirm'])->name('confirm');
        Route::delete('/', [VendorTwoFactorController::class, 'disable'])->name('disable');
    });

    Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [VendorAnalyticsController::class, 'index'])->name('analytics');

    Route::prefix('boost')->name('boost.')->group(function () {
        Route::get('/catalog', [VendorBoostController::class, 'catalog'])->name('catalog');
        Route::post('/request', [VendorBoostController::class, 'submitRequest'])->name('request');
        Route::middleware('purchase.email_verified')->group(function () {
            Route::post('/payment/init', [VendorBoostController::class, 'initPayment'])->name('payment.init');
            Route::post('/payment/resume', [VendorBoostController::class, 'resumePayment'])->name('payment.resume');
            Route::post('/payment/confirm', [VendorBoostController::class, 'confirmPayment'])->name('payment.confirm');
        });
    });

    Route::get('/payments/export', [VendorPaymentsController::class, 'export'])->name('payments.export');
    Route::get('/payments', [VendorPaymentsController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [VendorPaymentsController::class, 'show'])->name('payments.show');

    Route::get('/payment-methods', [VendorPaymentMethodsController::class, 'index'])->name('payment-methods.index');
    Route::middleware('purchase.email_verified')->group(function () {
        Route::post('/payment-methods', [VendorPaymentMethodsController::class, 'store'])->name('payment-methods.store');
    });
    Route::patch('/payment-methods/{paymentMethod}/default', [VendorPaymentMethodsController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('/payment-methods/{paymentMethod}', [VendorPaymentMethodsController::class, 'destroy'])->name('payment-methods.destroy');

    Route::get('/admin-chat', [VendorAdminChatController::class, 'show'])->name('admin-chat.show');

    Route::prefix('verification')->name('verification.')->group(function () {
        Route::get('/packages', [VendorVerificationController::class, 'packages'])->name('packages');
        Route::middleware('purchase.email_verified')->group(function () {
            Route::post('/payment/init', [VendorVerificationController::class, 'initPayment'])->name('payment.init');
            Route::post('/payment/confirm', [VendorVerificationController::class, 'confirmPayment'])->name('payment.confirm');
        });
        Route::post('/apply', [VendorVerificationController::class, 'apply'])->name('apply');
        Route::post('/documents/upload', [VendorVerificationController::class, 'uploadDocument'])->name('documents.upload');
        Route::get('/status', [VendorVerificationController::class, 'status'])->name('status');
    });

    Route::middleware('vendor.subscription')->group(function () {
        Route::get('/business/show', [BusinessInfoController::class, 'show'])->name('business.show');
        Route::put('/business/update', [BusinessInfoController::class, 'update'])->name('business.update');
        Route::post('/business/update', [BusinessInfoController::class, 'update'])->name('business.update.post');

        Route::middleware('vendor.premium')->group(function () {
            Route::post('/business/boost-status', [BusinessInfoController::class, 'updateBoostStatus'])->name('business.boost-status');
        });

        // Review Management Routes
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [VendorReviewController::class, 'index'])->name('index');
            Route::get('/statistics', [VendorReviewController::class, 'statistics'])->name('statistics');
            Route::get('/{review}', [VendorReviewController::class, 'show'])->name('show');
            Route::get('/{review}/replies', [VendorReviewController::class, 'replies'])->name('replies');
            Route::post('/{review}/reply', [VendorReviewController::class, 'reply'])->name('reply');
            Route::put('/replies/{reply}', [VendorReviewController::class, 'updateReply'])->name('update-reply');
            Route::delete('/replies/{reply}', [VendorReviewController::class, 'deleteReply'])->name('delete-reply');
        });
    });
});
