<?php

use App\Http\Controllers\Admin\AiSettingController;
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
    });

    Route::prefix('ia')->name('ai.')->group(function () {
        Route::get('/', [AiSettingController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/', [AiSettingController::class, 'update'])->name('update');
        Route::post('/testar', [AiSettingController::class, 'test'])->name('test');
    });
});
