<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\CondominiumController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\EvolutionWebhookController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Health check (sem autenticação)
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'version' => config('app.version', '1.0.0'),
    'timestamp' => now()->toIso8601String(),
]));

// Webhooks de gateways (públicos, sem tenant por host — resolvido no controller pelo payload)
Route::post('/webhooks/asaas', [WebhookController::class, 'asaas'])->name('webhooks.asaas');
Route::post('/webhooks/evolution', [EvolutionWebhookController::class, 'handle'])->name('webhooks.evolution');

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

    // API pública por API Key (Bearer sk_...). Tenant resolvido pelo host + asserção na chave.
    Route::middleware(['api.key', 'api.log'])->name('api.v1.')->group(function () {
        // Condomínios
        Route::get('condominiums', [CondominiumController::class, 'index'])->middleware('api.scope:condominiums:read')->name('condominiums.index');
        Route::post('condominiums', [CondominiumController::class, 'store'])->middleware('api.scope:condominiums:write')->name('condominiums.store');
        Route::get('condominiums/{id}', [CondominiumController::class, 'show'])->middleware('api.scope:condominiums:read')->name('condominiums.show');
        Route::put('condominiums/{id}', [CondominiumController::class, 'update'])->middleware('api.scope:condominiums:write')->name('condominiums.update');

        // Unidades
        Route::get('units', [UnitController::class, 'index'])->middleware('api.scope:units:read')->name('units.index');
        Route::post('units', [UnitController::class, 'store'])->middleware('api.scope:units:write')->name('units.store');
        Route::get('units/{id}', [UnitController::class, 'show'])->middleware('api.scope:units:read')->name('units.show');
        Route::put('units/{id}', [UnitController::class, 'update'])->middleware('api.scope:units:write')->name('units.update');

        // Pessoas
        Route::get('persons', [PersonController::class, 'index'])->middleware('api.scope:persons:read')->name('persons.index');
        Route::post('persons', [PersonController::class, 'store'])->middleware('api.scope:persons:write')->name('persons.store');
        Route::get('persons/{id}', [PersonController::class, 'show'])->middleware('api.scope:persons:read')->name('persons.show');
        Route::put('persons/{id}', [PersonController::class, 'update'])->middleware('api.scope:persons:write')->name('persons.update');

        // Cobranças
        Route::get('charges', [ChargeController::class, 'index'])->middleware('api.scope:charges:read')->name('charges.index');
        Route::post('charges', [ChargeController::class, 'store'])->middleware('api.scope:charges:write')->name('charges.store');
        Route::get('charges/{id}', [ChargeController::class, 'show'])->middleware('api.scope:charges:read')->name('charges.show');
    });
});
