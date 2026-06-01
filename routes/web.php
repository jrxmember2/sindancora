<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Panel\AuditController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\RoleController;
use App\Http\Controllers\Panel\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Onboarding público (cadastro de novo tenant)
Route::middleware('guest')->group(function () {
    Route::get('/cadastro', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/cadastro', [OnboardingController::class, 'store'])->name('onboarding.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Usuários
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names([
        'index' => 'users.index',
        'create' => 'users.create',
        'store' => 'users.store',
        'edit' => 'users.edit',
        'update' => 'users.update',
        'destroy' => 'users.destroy',
    ]);

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    // Auditoria
    Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
});

require __DIR__.'/auth.php';
