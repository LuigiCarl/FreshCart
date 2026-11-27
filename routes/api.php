<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:10,1')
            ->name('register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum')
            ->name('logout');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('orders.store');
        Route::get('/orders/{order_number}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order_number}/image', [OrderController::class, 'uploadImage'])
            ->middleware('throttle:10,1')
            ->name('orders.image.store');
        Route::get('/orders/{order_number}/image', [OrderController::class, 'showImage'])
            ->name('orders.image.show');
    });
});
