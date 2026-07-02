<?php

use App\Http\Controllers\Api\V1\BusinessReportController;
use App\Http\Controllers\Api\V1\ReviewReportController;
use App\Http\Controllers\Api\V1\UserBusinessController;
use App\Http\Controllers\Api\V1\UserFavoritesController;
use App\Http\Controllers\Api\V1\UserFollowController;
use App\Http\Controllers\Api\V1\UserModeController;
use App\Http\Controllers\Api\V1\UserReferralController;
use App\Http\Controllers\Api\V1\UserReviewsController;
use App\Http\Controllers\Api\V1\UserSettingsController;
use App\Http\Controllers\Api\V1\UserWalletController;
use App\Http\Controllers\Api\V1\Vendor\VendorOnboardingController;
use App\Http\Controllers\Api\V1\Vendor\VendorSubscriptionController;
use App\Http\Controllers\Api\V1\BusinessInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User / Vendor API Routes (role: user, vendor)
| Middleware: auth:api, role:user,vendor (verified applied per-route below)
|--------------------------------------------------------------------------
*/

Route::prefix('user')->name('user.')->group(function () {
    // Account setup — available before registration OTP is confirmed.
    Route::get('/settings', [UserSettingsController::class, 'show'])->name('settings.show');
    // POST required for multipart image uploads (PHP/nginx do not parse files on PATCH in production).
    Route::match(['patch', 'post'], '/settings', [UserSettingsController::class, 'update'])->name('settings.update');
    Route::post('/password', [UserSettingsController::class, 'changePassword'])->name('password.change');

    Route::prefix('email')->name('email.')->group(function () {
        Route::post('/', [UserSettingsController::class, 'updateEmail'])->middleware('throttle:6,1')->name('update');
        Route::post('/verify-otp', [UserSettingsController::class, 'verifyEmailOtp'])->middleware('throttle:10,1')->name('verify-otp');
        Route::post('/resend-otp', [UserSettingsController::class, 'resendEmailOtp'])->middleware('throttle:6,1')->name('resend-otp');
    });

    Route::middleware('verified')->group(function (): void {
        Route::get('/dashboard', fn() => response()->json(['message' => 'User dashboard.']))->name('dashboard');

        Route::get('/profile', [UserSettingsController::class, 'profileShow'])->name('profile.show');
        Route::patch('/profile', [UserSettingsController::class, 'profileUpdate'])->name('profile.update');

        Route::prefix('favorites')->name('favorites.')->group(function () {
            Route::get('/', [UserFavoritesController::class, 'index'])->name('index');
            Route::post('/toggle', [UserFavoritesController::class, 'toggle'])->name('toggle');
            Route::delete('/{businessId}', [UserFavoritesController::class, 'destroy'])->name('destroy');
            Route::get('/{businessId}/exists', [UserFavoritesController::class, 'exists'])->name('exists');
        });

        Route::prefix('follows')->name('follows.')->group(function () {
            Route::get('/stats', [UserFollowController::class, 'stats'])->name('stats');
            Route::get('/followers', [UserFollowController::class, 'followers'])->name('followers');
            Route::get('/following', [UserFollowController::class, 'following'])->name('following');
            Route::post('/toggle', [UserFollowController::class, 'toggle'])->name('toggle');
        });

        Route::prefix('mode')->name('mode.')->group(function () {
            Route::post('/vendor', [UserModeController::class, 'switchToVendor'])->name('vendor');
            Route::post('/customer', [UserModeController::class, 'switchToCustomer'])->name('customer');
        });

        Route::get('/reviews', [UserReviewsController::class, 'index'])->name('reviews.index');

        Route::get('/businesses', [UserBusinessController::class, 'index'])->name('businesses.index');
        Route::post('/businesses', [UserBusinessController::class, 'store'])->name('businesses.store');
        Route::delete('/businesses/{businessInfo}', [UserBusinessController::class, 'destroy'])->name('businesses.destroy');

        Route::get('/wallet', [UserWalletController::class, 'show'])->name('wallet.show');
        Route::middleware('purchase.email_verified')->group(function (): void {
            Route::post('/wallet/top-up', [UserWalletController::class, 'initTopUp'])->name('wallet.top-up');
            Route::post('/wallet/top-up/confirm', [UserWalletController::class, 'confirmTopUp'])->name('wallet.top-up.confirm');
        });

        Route::get('/referrals', [UserReferralController::class, 'show'])->name('referrals.show');

        Route::post('/reviews/{review}/report', [ReviewReportController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('reviews.report');

        Route::post('/businesses/{businessInfo}/report', [BusinessReportController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('businesses.report');
    });
});

/**
 * Vendor onboarding status is used by the public Trade flow.
 * Allow both customers and vendors to fetch it so a logged-in customer can
 * choose a plan and begin vendor onboarding without logging out.
 */
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/onboarding/status', [VendorOnboardingController::class, 'status'])->name('onboarding.status');

    Route::middleware('verified')->group(function (): void {
        // Onboarding endpoints — verified account required (signup OTP must be completed).
        Route::get('/business/form-options', [BusinessInfoController::class, 'formOptions'])->name('business.form-options');
        Route::post('/business/create', [BusinessInfoController::class, 'store'])->name('business.create');

        Route::prefix('subscription')->name('subscription.')->group(function () {
            Route::get('/packages', [VendorSubscriptionController::class, 'packages'])->name('packages');
            Route::get('/status', [VendorSubscriptionController::class, 'status'])->name('status');
            Route::middleware('purchase.email_verified')->group(function () {
                Route::post('/payment/init', [VendorSubscriptionController::class, 'initPayment'])->name('payment.init');
                Route::post('/payment/resume', [VendorSubscriptionController::class, 'resumePayment'])->name('payment.resume');
                Route::post('/payment/confirm', [VendorSubscriptionController::class, 'confirmPayment'])->name('payment.confirm');
                Route::post('/payment/reconcile', [VendorSubscriptionController::class, 'reconcilePayment'])->name('payment.reconcile');
            });
        });
    });
});
