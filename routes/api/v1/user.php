<?php

use App\Http\Controllers\Api\V1\BusinessReportController;
use App\Http\Controllers\Api\V1\ReviewReportController;
use App\Http\Controllers\Api\V1\UserFavoritesController;
use App\Http\Controllers\Api\V1\UserReviewsController;
use App\Http\Controllers\Api\V1\UserSettingsController;
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
