<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

// Health check (sem autenticação)
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => config('app.version', '1.0.0'),
    'timestamp' => now()->toIso8601String(),
]));

// API v1
Route::prefix('v1')->group(function () {

    // Autenticação (sem middleware de auth)
    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    // Rotas autenticadas
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');

        // Tenant
        Route::prefix('tenants')->name('api.v1.tenants.')->group(function () {
            Route::get('current', [TenantController::class, 'current'])->name('current');
            Route::get('current/usage', [TenantController::class, 'usage'])->name('usage');
            Route::get('current/storage', [TenantController::class, 'storageStats'])->name('storage');
        });
    });
});
