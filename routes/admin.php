<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\DashboardController;
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
});
