<?php

use App\Http\Controllers\Admin\AiSettingController;
use App\Http\Controllers\Admin\Billing\BillingDashboardController;
use App\Http\Controllers\Admin\Billing\PaymentController as BillingPaymentController;
use App\Http\Controllers\Admin\Billing\SettingController as BillingSettingController;
use App\Http\Controllers\Admin\Billing\SubscriptionController as BillingSubscriptionController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EvolutionSettingController;
use App\Http\Controllers\Admin\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])->name('index');
        Route::get('/create', [TenantController::class, 'create'])->name('create');
        Route::post('/', [TenantController::class, 'store'])->name('store');
        Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
        Route::patch('/{tenant}/suspend', [TenantController::class, 'suspend'])->name('suspend');
        Route::patch('/{tenant}/activate', [TenantController::class, 'activate'])->name('activate');
        Route::patch('/{tenant}/plan', [TenantController::class, 'changePlan'])->name('change-plan');
        Route::patch('/{tenant}/ai-limit', [TenantController::class, 'updateAiLimit'])->name('ai-limit.update');
        Route::delete('/{tenant}/ai-limit', [TenantController::class, 'destroyAiLimit'])->name('ai-limit.destroy');
    });

    Route::prefix('planos')->name('plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index');
        Route::get('/create', [PlanController::class, 'create'])->name('create');
        Route::post('/', [PlanController::class, 'store'])->name('store');
        Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{plan}', [PlanController::class, 'update'])->name('update');
        Route::patch('/{plan}/toggle', [PlanController::class, 'toggleActive'])->name('toggle');
    });

    // Configuração global do servidor Evolution (WhatsApp)
    Route::prefix('whatsapp')->name('evolution.')->group(function () {
        Route::get('/', [EvolutionSettingController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/', [EvolutionSettingController::class, 'update'])->name('update');
        Route::post('/testar', [EvolutionSettingController::class, 'test'])->name('test');
        Route::post('/resync-webhooks', [EvolutionSettingController::class, 'resyncWebhooks'])->name('webhooks.resync');
    });

    // Módulo Financeiro (billing SaaS): dashboard, assinaturas, pagamentos/NFS-e e configurações.
    Route::prefix('financeiro')->name('billing.')->group(function () {
        Route::get('/', [BillingDashboardController::class, 'index'])->name('dashboard');

        Route::get('/assinaturas', [BillingSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/assinaturas/{subscription}', [BillingSubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('/assinaturas/{subscription}/desbloquear', [BillingSubscriptionController::class, 'grantGrace'])->name('subscriptions.grace');
        Route::post('/assinaturas/{subscription}/revogar-carencia', [BillingSubscriptionController::class, 'revokeGrace'])->name('subscriptions.revoke-grace');
        Route::post('/assinaturas/{subscription}/bloquear', [BillingSubscriptionController::class, 'suspend'])->name('subscriptions.suspend');
        Route::post('/assinaturas/{subscription}/cancelar', [BillingSubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

        Route::get('/pagamentos', [BillingPaymentController::class, 'index'])->name('payments.index');

        Route::get('/configuracoes', [BillingSettingController::class, 'edit'])->name('settings.edit');
        Route::match(['put', 'patch'], '/configuracoes', [BillingSettingController::class, 'update'])->name('settings.update');
        Route::post('/configuracoes/testar', [BillingSettingController::class, 'testConnection'])->name('settings.test');
    });

    Route::prefix('ia')->name('ai.')->group(function () {
        Route::get('/', [AiSettingController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/', [AiSettingController::class, 'update'])->name('update');
        Route::post('/testar', [AiSettingController::class, 'test'])->name('test');
        Route::post('/documentos-legais', [AiSettingController::class, 'storeLegalDocument'])->name('legal-documents.store');
        Route::patch('/documentos-legais/{document}/toggle', [AiSettingController::class, 'toggleLegalDocument'])->name('legal-documents.toggle');
        Route::post('/documentos-legais/{document}/reindexar', [AiSettingController::class, 'reindexLegalDocument'])->name('legal-documents.reindex');
        Route::get('/documentos-legais/{document}/download', [AiSettingController::class, 'downloadLegalDocument'])->name('legal-documents.download');
        Route::delete('/documentos-legais/{document}', [AiSettingController::class, 'destroyLegalDocument'])->name('legal-documents.destroy');
    });
});
