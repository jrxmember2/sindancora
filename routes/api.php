<?php

use App\Http\Controllers\Api\V1\App\AnnouncementController as AppAnnouncementController;
use App\Http\Controllers\Api\V1\App\DashboardController as AppDashboardController;
use App\Http\Controllers\Api\V1\App\DeviceController as AppDeviceController;
use App\Http\Controllers\Api\V1\App\FinancialController as AppFinancialController;
use App\Http\Controllers\Api\V1\App\GatehouseController as AppGatehouseController;
use App\Http\Controllers\Api\V1\App\OccurrenceController as AppOccurrenceController;
use App\Http\Controllers\Api\V1\App\ReservationController as AppReservationController;
use App\Http\Controllers\Api\V1\App\ScheduleController as AppScheduleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\CondominiumController;
use App\Http\Controllers\Api\V1\InstanceController;
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
// Billing SaaS (conta Asaas única da plataforma). Distinto do webhook tenant→morador acima.
// Idempotente, validado por token e processado em fila. Rate limit alto p/ absorver retries.
Route::post('/webhooks/asaas/saas', [\App\Http\Controllers\Api\Billing\AsaasWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.asaas.saas');
// O segredo no caminho é conferido no controller (impede POSTs forjados na inbox dos tenants).
Route::post('/webhooks/evolution/{secret?}', [EvolutionWebhookController::class, 'handle'])->name('webhooks.evolution');

// API v1
Route::prefix('v1')->group(function () {

    // Descoberta de instância para o app móvel (pública; tenant resolvido pelo host).
    Route::get('instance-info', [InstanceController::class, 'show'])->name('api.v1.instance-info');

    // Autenticação (sem middleware de auth)
    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });
    });

    // Rotas autenticadas
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::get('session', [AuthController::class, 'session'])->name('api.v1.session');

        // Dispositivos do app (push FCM)
        Route::post('devices', [AppDeviceController::class, 'store'])->name('api.v1.devices.store');
        Route::delete('devices', [AppDeviceController::class, 'destroy'])->name('api.v1.devices.destroy');

        // Tenant
        Route::prefix('tenants')->name('api.v1.tenants.')->group(function () {
            Route::get('current', [TenantController::class, 'current'])->name('current');
            Route::get('current/usage', [TenantController::class, 'usage'])->name('usage');
            Route::get('current/storage', [TenantController::class, 'storageStats'])->name('storage');
        });
    });

    // App móvel do tenant (síndico) — token de usuário Sanctum + MESMO gating
    // permissão/módulo do painel (middleware permission: valida módulo do plano).
    Route::prefix('app')->name('api.v1.app.')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        // Dashboard (gating por widget dentro do WidgetRegistry)
        Route::get('dashboard', [AppDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/widgets/{key}', [AppDashboardController::class, 'widget'])->name('dashboard.widget');

        // Cronograma consolidado (gating por fonte dentro do ScheduleEventBuilder)
        Route::middleware('permission:schedule:read')->group(function () {
            Route::get('schedule', [AppScheduleController::class, 'index'])->name('schedule');
        });

        // Comunicados
        Route::middleware('permission:announcements:read')->group(function () {
            Route::get('announcements', [AppAnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('announcements/{announcement}', [AppAnnouncementController::class, 'show'])->name('announcements.show');
        });
        Route::middleware('permission:announcements:create')->group(function () {
            Route::post('announcements', [AppAnnouncementController::class, 'store'])->name('announcements.store');
        });
        Route::middleware('permission:announcements:publish')->group(function () {
            Route::post('announcements/{announcement}/publish', [AppAnnouncementController::class, 'publish'])->name('announcements.publish');
        });

        // Ocorrências (status/responsável/comentários sob occurrences:update, como no painel)
        Route::middleware('permission:occurrences:read')->group(function () {
            Route::get('occurrences', [AppOccurrenceController::class, 'index'])->name('occurrences.index');
            Route::get('occurrences/{occurrence}', [AppOccurrenceController::class, 'show'])->name('occurrences.show');
        });
        Route::middleware('permission:occurrences:create')->group(function () {
            Route::post('occurrences', [AppOccurrenceController::class, 'store'])->name('occurrences.store');
        });
        Route::middleware('permission:occurrences:update')->group(function () {
            Route::post('occurrences/{occurrence}/status', [AppOccurrenceController::class, 'changeStatus'])->name('occurrences.status');
            Route::post('occurrences/{occurrence}/assign', [AppOccurrenceController::class, 'assign'])->name('occurrences.assign');
            Route::post('occurrences/{occurrence}/comments', [AppOccurrenceController::class, 'addComment'])->name('occurrences.comments');
        });

        // Reservas
        Route::middleware('permission:reservations:read')->group(function () {
            Route::get('reservations', [AppReservationController::class, 'index'])->name('reservations.index');
            Route::get('reservations/{reservation}', [AppReservationController::class, 'show'])->name('reservations.show');
        });
        Route::middleware('permission:reservations:approve')->group(function () {
            Route::post('reservations/{reservation}/approve', [AppReservationController::class, 'approve'])->name('reservations.approve');
        });
        Route::middleware('permission:reservations:reject')->group(function () {
            Route::post('reservations/{reservation}/reject', [AppReservationController::class, 'reject'])->name('reservations.reject');
        });
        Route::middleware('permission:reservations:cancel')->group(function () {
            Route::post('reservations/{reservation}/cancel', [AppReservationController::class, 'cancel'])->name('reservations.cancel');
        });

        // Financeiro (leitura)
        Route::middleware('permission:charges:read')->group(function () {
            Route::get('charges', [AppFinancialController::class, 'charges'])->name('charges.index');
            Route::get('charges/{charge}', [AppFinancialController::class, 'chargeShow'])->name('charges.show');
        });
        Route::middleware('permission:expenses:read')->group(function () {
            Route::get('expenses', [AppFinancialController::class, 'expenses'])->name('expenses.index');
        });

        // Portaria e encomendas
        Route::middleware('permission:gatehouse:read')->group(function () {
            Route::get('gatehouse/visits', [AppGatehouseController::class, 'visits'])->name('gatehouse.visits');
            Route::get('parcels', [AppGatehouseController::class, 'parcels'])->name('parcels.index');
        });
        Route::middleware('permission:gatehouse:manage')->group(function () {
            Route::post('gatehouse/validate-token', [AppGatehouseController::class, 'validateToken'])->name('gatehouse.validate');
            Route::post('gatehouse/check-in', [AppGatehouseController::class, 'checkIn'])->name('gatehouse.check-in');
            Route::post('gatehouse/visits/{visit}/check-out', [AppGatehouseController::class, 'checkOut'])->name('gatehouse.check-out');
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
