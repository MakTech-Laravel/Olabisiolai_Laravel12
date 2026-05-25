<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

Route::post('presence/ping', [MessageController::class, 'presencePing'])->name('presence.ping');
Route::post('presence/offline', [MessageController::class, 'presenceOffline'])->name('presence.offline');

Route::get('conversations/search', [ConversationController::class, 'search'])->name('conversations.search');

Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');
Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
    ->middleware(['messaging.throttle', 'throttle:60,1'])
    ->name('conversations.messages.store');

Route::patch('messages/{message}', [MessageController::class, 'update'])->middleware(['throttle:60,1'])->name('messages.update');
Route::delete('messages/{message}', [MessageController::class, 'destroy'])->middleware(['throttle:60,1'])->name('messages.destroy');
Route::post('messages/{message}/read', [MessageController::class, 'markRead'])->middleware(['throttle:60,1'])->name('messages.read');

Route::post('conversations/{conversation}/typing', [MessageController::class, 'typing'])
    ->name('conversations.typing');

Route::post('attachments', [AttachmentController::class, 'store'])->middleware(['throttle:60,1'])->name('attachments.store');
Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->middleware(['throttle:60,1'])->name('attachments.destroy');
