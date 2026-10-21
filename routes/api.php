<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'show']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('orders', OrderController::class);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/orders/{order}/payments', [PaymentController::class, 'forOrder']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
});
