<?php

use App\Http\Controllers\Api\AdminDishController;
use App\Http\Controllers\Api\AdminStatsController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StaffAuthController;
use App\Http\Controllers\Api\WaiterCallController;
use Illuminate\Support\Facades\Route;

Route::middleware('telegram.auth')->group(function () {
    Route::post('/session', [SessionController::class, 'resolve']);

    Route::get('/menu', [MenuController::class, 'index']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);

    Route::get('/waiter-calls', [WaiterCallController::class, 'index']);
    Route::post('/waiter-calls', [WaiterCallController::class, 'store']);
});

Route::prefix('staff')->group(function () {
    Route::middleware('staff.auth')->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me']);

        Route::get('/orders', [OrderController::class, 'staffIndex']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

        Route::get('/waiter-calls', [WaiterCallController::class, 'staffIndex']);
        Route::patch('/waiter-calls/{waiterCall}/status', [WaiterCallController::class, 'updateStatus']);

        Route::middleware('staff.admin')->prefix('admin')->group(function () {
            Route::get('/dishes', [AdminDishController::class, 'index']);
            Route::patch('/dishes/{dish}/availability', [AdminDishController::class, 'toggleAvailability']);
            Route::post('/dishes/{dish}/discount', [AdminDishController::class, 'setDiscount']);
            Route::delete('/discounts', [AdminDishController::class, 'clearDiscount']);
            Route::get('/stats', [AdminStatsController::class, 'index']);
        });
    });
});
