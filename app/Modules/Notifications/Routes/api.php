<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->middleware('auth:api')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::patch('{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
});
