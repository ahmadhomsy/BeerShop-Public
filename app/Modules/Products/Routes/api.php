<?php

use App\Modules\Products\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('{id}', [ProductController::class, 'show'])->whereNumber('id');

    Route::middleware(['auth:api', 'role:admin,seller'])->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::match(['put', 'patch'], '{id}', [ProductController::class, 'update'])->whereNumber('id');
    });
});
