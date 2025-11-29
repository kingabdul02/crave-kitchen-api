<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // User Profile routes
    Route::prefix('profile')->group(function () {
        Route::put('/', [\App\Http\Controllers\UserController::class, 'updateProfile']);
        Route::put('/password', [\App\Http\Controllers\UserController::class, 'updatePassword']);
    });

    // Legacy user route for compatibility
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin API routes (will be added as we implement features)
    Route::prefix('admin')->group(function () {
        // Customers routes
        Route::apiResource('customers', \App\Http\Controllers\CustomerController::class);
        Route::delete('customers/{customer}/soft-delete', [\App\Http\Controllers\CustomerController::class, 'softDelete']);
        Route::get('customers/{customer}/statistics', [\App\Http\Controllers\CustomerController::class, 'statistics']);

        // Items routes
        Route::apiResource('items', \App\Http\Controllers\ItemController::class);
        Route::get('items-categories', [\App\Http\Controllers\ItemController::class, 'categories']);
        Route::put('items/{item}/stock', [\App\Http\Controllers\ItemController::class, 'updateStock']);
        Route::post('items/check-availability', [\App\Http\Controllers\ItemController::class, 'checkAvailability']);
        Route::get('items-low-stock', [\App\Http\Controllers\ItemController::class, 'lowStock']);

        // Orders routes
        Route::apiResource('orders', \App\Http\Controllers\OrderController::class);
        Route::get('orders-summary', [\App\Http\Controllers\OrderController::class, 'summary']);
        Route::put('orders/{order}/status', [\App\Http\Controllers\OrderController::class, 'updateStatus']);
        Route::put('orders/{order}/payment-status', [\App\Http\Controllers\OrderController::class, 'updatePaymentStatus']);
        Route::put('orders/{order}/settled', [\App\Http\Controllers\OrderController::class, 'updateSettled']);

        // Payments routes
        Route::prefix('orders/{order}')->group(function () {
            Route::get('payments', [\App\Http\Controllers\PaymentController::class, 'index']);
            Route::post('payments', [\App\Http\Controllers\PaymentController::class, 'store']);
            Route::get('payments/summary', [\App\Http\Controllers\PaymentController::class, 'summary']);
            Route::get('payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'show']);
            Route::put('payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'update']);
            Route::delete('payments/{payment}', [\App\Http\Controllers\PaymentController::class, 'destroy']);
        });

        // Dashboard routes
        Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
        Route::get('dashboard/recent-activity', [\App\Http\Controllers\DashboardController::class, 'recentActivity']);

        // Search routes
        Route::prefix('search')->group(function () {
            Route::get('orders', [\App\Http\Controllers\OrderController::class, 'search']);
            Route::get('customers', [\App\Http\Controllers\CustomerController::class, 'search']);
            Route::get('items', [\App\Http\Controllers\ItemController::class, 'search']);
            Route::get('global', [\App\Http\Controllers\SearchController::class, 'globalSearch']);
            Route::get('suggestions', [\App\Http\Controllers\SearchController::class, 'suggestions']);
        });

        // Error logging routes
        Route::prefix('errors')->group(function () {
            Route::post('/', [\App\Http\Controllers\ErrorLogController::class, 'logError']);
            Route::post('batch', [\App\Http\Controllers\ErrorLogController::class, 'logErrorBatch']);
            Route::get('stats', [\App\Http\Controllers\ErrorLogController::class, 'getErrorStats']);
        });
    });
});

// Public error logging routes (for unauthenticated errors)
Route::prefix('errors')->group(function () {
    Route::post('public', [\App\Http\Controllers\ErrorLogController::class, 'logError']);
    Route::get('health', [\App\Http\Controllers\ErrorLogController::class, 'health']);
});
