<?php

use App\Modules\Orders\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->middleware('auth:api')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('{id}', [OrderController::class, 'show'])->whereNumber('id');

    // Only admin can change order status
    Route::patch('{id}/status', [OrderController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('role:admin');
});
