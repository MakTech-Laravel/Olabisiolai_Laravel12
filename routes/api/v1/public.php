<?php

use App\Http\Controllers\Api\V1\BusinessReportController;
use App\Http\Controllers\Api\V1\Public\BusinessInfoController;
use App\Http\Controllers\Api\V1\Public\ContactMessageController;
use App\Http\Controllers\Api\V1\Public\PublicCategoryCatalogController;
use App\Http\Controllers\Api\V1\Public\PublicCmsPageController;
use App\Http\Controllers\Api\V1\Public\PublicLocationCatalogController;
use App\Http\Controllers\Api\V1\Public\PublicSubscriptionPlanController;
use App\Http\Controllers\Api\V1\Public\ReviewController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\Webhooks\PaystackWebhookController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes (no authentication required)
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.paystack');

Route::post('/contact-messages', [ContactMessageController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact-messages.store');

Route::get('/categories', [PublicCategoryCatalogController::class, 'index'])->name('public.categories');
Route::get('/categories/{category}', [PublicCategoryCatalogController::class, 'show'])->name('public.categories.show');
Route::get('/locations', [PublicLocationCatalogController::class, 'index'])->name('public.locations');
Route::get('/subscription-packages', [PublicSubscriptionPlanController::class, 'index'])->name('public.subscription-packages');

Route::prefix('businesses')->name('businesses.')->group(function () {
    Route::get('/all', [BusinessInfoController::class, 'all'])->name('all');
    Route::get('/home', [BusinessInfoController::class, 'homePage'])->name('home');
    Route::get('/featured', [BusinessInfoController::class, 'featured'])->name('featured');
    Route::get('/search', [BusinessInfoController::class, 'search'])->name('search');
    Route::get('/{businessId}', [BusinessInfoController::class, 'show'])
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ])
        ->name('show');
});

Route::get('/business-report-reasons', [BusinessReportController::class, 'reasons'])
    ->name('business-report-reasons');

Route::get('/review-report-reasons', [BusinessReportController::class, 'reasons'])
    ->name('review-report-reasons');

Route::prefix('reviews')->name('reviews.')->group(function () {
    Route::post('/', [ReviewController::class, 'index'])->name('index');
    Route::post('/store', [ReviewController::class, 'store'])
        ->middleware('auth:api')
        ->name('store');
});

Route::get('/about', [PublicCmsPageController::class, 'show'])->defaults('slug', 'about')->name('about');
Route::get('/privacy-policy', [PublicCmsPageController::class, 'show'])->defaults('slug', 'privacy-policy')->name('privacy-policy');
Route::get('/terms', [PublicCmsPageController::class, 'show'])->defaults('slug', 'terms')->name('terms');

// Public realtime diagnostics (used by the frontend /ws-test console).
Route::controller(RealtimeController::class)->prefix('realtime')->group(function () {
    Route::get('/ping', 'ping')->middleware('throttle:30,1')->name('api.v1.realtime.ping');
    Route::get('/health', 'health')->name('api.v1.realtime.health');
});
