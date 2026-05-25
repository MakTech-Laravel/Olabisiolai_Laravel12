<?php

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1/auth.php'));

Route::middleware(['auth:api,admin_api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('notifications/read-bulk', [NotificationController::class, 'markBulkRead'])->name('notifications.read-bulk');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    });

Route::middleware(['auth:admin_api', 'admin', 'verified'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/admin.php'));

Route::middleware(['auth:api', 'verified', 'role:user,vendor'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/user.php'));

Route::middleware(['auth:api', 'verified', 'role:user,vendor', 'messaging.presence'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/messaging.php'));

Route::middleware(['auth:api', 'verified', 'role:vendor'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/vendor.php'));

// Route::middleware(['auth:api'])
//     ->prefix('v1')
//     ->name('api.v1.')
//     ->group(base_path('routes/api/v1/frontend.php'));

Route::prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/public.php'));
