<?php

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', 'register');
        Route::post('/otp/verify', 'verifyOtp');
        Route::post('/login', 'login');
        Route::post('/phone/request-otp', 'requestPhoneLoginOtp')->middleware('throttle:5,1');
        Route::post('/phone/verify-otp', 'verifyPhoneLoginOtp')->middleware('throttle:10,1');
        Route::post('/phone/resend-otp', 'resendPhoneLoginOtp')->middleware('throttle:3,1');
        Route::post('/two-factor/verify', 'verifyTwoFactorLogin');
        Route::post('/admin/login', 'adminLogin');
        Route::post('/forgot-password', 'forgotPassword')->withoutMiddleware([ValidateCsrfToken::class]);
        Route::post('/forgot-password/resend-otp', 'resendForgotPasswordOtp')
            ->withoutMiddleware([ValidateCsrfToken::class]);
        Route::post('/forgot-password/verify-otp', 'verifyForgotPasswordOtp')
            ->withoutMiddleware([ValidateCsrfToken::class]);
        Route::post('/forgot-password/verify-token', 'verifyForgotPasswordToken')
            ->withoutMiddleware([ValidateCsrfToken::class]);
        Route::post('/reset-password', 'resetPassword')
            ->withoutMiddleware([ValidateCsrfToken::class]);
    });
});

Route::middleware('auth:api,admin_api')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/otp/resend', 'resendOtp')->withoutMiddleware([ValidateCsrfToken::class]);
            Route::post('/logout', 'logout');
            Route::get('/profile', 'profile');
        });
    });
});

Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
});

Route::prefix('admin')
    ->middleware(['auth:admin_api'])
    ->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);
    });
