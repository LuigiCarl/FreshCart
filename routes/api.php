<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderImageController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth')
            ->name('register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth')
            ->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum')
            ->name('logout');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware('throttle:checkout')
            ->name('orders.store');
        Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])
            ->middleware('can:view,order')
            ->name('orders.show');
        Route::post('/orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])
            ->middleware('can:cancel,order')
            ->name('orders.cancel');
        Route::post('/orders/{order:order_number}/image', [OrderImageController::class, 'store'])
            ->middleware(['throttle:order-images', 'can:uploadImage,order'])
            ->name('orders.image.store');
        Route::get('/orders/{order:order_number}/image', [OrderImageController::class, 'show'])
            ->middleware('can:view,order')
            ->name('orders.image.show');
    });
});
