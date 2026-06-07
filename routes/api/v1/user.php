<?php

use App\Http\Controllers\Api\V1\BusinessReportController;
use App\Http\Controllers\Api\V1\ReviewReportController;
use App\Http\Controllers\Api\V1\UserFavoritesController;
use App\Http\Controllers\Api\V1\UserReviewsController;
use App\Http\Controllers\Api\V1\UserSettingsController;
use App\Http\Controllers\Api\V1\Vendor\VendorOnboardingController;
use App\Http\Controllers\Api\V1\Vendor\VendorSubscriptionController;
use App\Http\Controllers\Api\V1\BusinessInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User / Vendor API Routes (role: user, vendor)
| Middleware: auth:api, verified, role:user,vendor
|--------------------------------------------------------------------------
*/

Route::prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', fn() => response()->json(['message' => 'User dashboard.']))->name('dashboard');

    Route::get('/profile', [UserSettingsController::class, 'profileShow'])->name('profile.show');
    Route::patch('/profile', [UserSettingsController::class, 'profileUpdate'])->name('profile.update');

    Route::get('/settings', [UserSettingsController::class, 'show'])->name('settings.show');
    // POST required for multipart image uploads (PHP/nginx do not parse files on PATCH in production).
    Route::match(['patch', 'post'], '/settings', [UserSettingsController::class, 'update'])->name('settings.update');

    Route::post('/password', [UserSettingsController::class, 'changePassword'])->name('password.change');

    Route::prefix('email')->name('email.')->group(function () {
        Route::post('/', [UserSettingsController::class, 'updateEmail'])->middleware('throttle:6,1')->name('update');
        Route::post('/verify-otp', [UserSettingsController::class, 'verifyEmailOtp'])->middleware('throttle:10,1')->name('verify-otp');
        Route::post('/resend-otp', [UserSettingsController::class, 'resendEmailOtp'])->middleware('throttle:6,1')->name('resend-otp');
    });

    Route::prefix('favorites')->name('favorites.')->group(function () {
        Route::get('/', [UserFavoritesController::class, 'index'])->name('index');
        Route::post('/toggle', [UserFavoritesController::class, 'toggle'])->name('toggle');
        Route::delete('/{businessId}', [UserFavoritesController::class, 'destroy'])->name('destroy');
        Route::get('/{businessId}/exists', [UserFavoritesController::class, 'exists'])->name('exists');
    });

    Route::get('/reviews', [UserReviewsController::class, 'index'])->name('reviews.index');

    Route::post('/reviews/{review}/report', [ReviewReportController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('reviews.report');

    Route::post('/businesses/{businessInfo}/report', [BusinessReportController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('businesses.report');
});

/**
 * Vendor onboarding status is used by the public Trade flow.
 * Allow both customers and vendors to fetch it so a logged-in customer can
 * choose a plan and begin vendor onboarding without logging out.
 */
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/onboarding/status', [VendorOnboardingController::class, 'status'])->name('onboarding.status');

    // Onboarding endpoints needed by Trade → Choose plan → Create listing.
    Route::get('/business/form-options', [BusinessInfoController::class, 'formOptions'])->name('business.form-options');
    Route::post('/business/create', [BusinessInfoController::class, 'store'])->name('business.create');

    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/packages', [VendorSubscriptionController::class, 'packages'])->name('packages');
        Route::get('/status', [VendorSubscriptionController::class, 'status'])->name('status');
        Route::middleware('purchase.email_verified')->group(function () {
            Route::post('/payment/init', [VendorSubscriptionController::class, 'initPayment'])->name('payment.init');
            Route::post('/payment/resume', [VendorSubscriptionController::class, 'resumePayment'])->name('payment.resume');
            Route::post('/payment/confirm', [VendorSubscriptionController::class, 'confirmPayment'])->name('payment.confirm');
        });
    });
});
