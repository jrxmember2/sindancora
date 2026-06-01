<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Panel\AuditController;
use App\Http\Controllers\Panel\CondominiumController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\PersonController;
use App\Http\Controllers\Panel\RoleController;
use App\Http\Controllers\Panel\UnitController;
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

    // Condomínios
    Route::resource('condominios', CondominiumController::class)->parameters(['condominios' => 'condominium'])->names([
        'index' => 'condominiums.index',
        'create' => 'condominiums.create',
        'store' => 'condominiums.store',
        'show' => 'condominiums.show',
        'edit' => 'condominiums.edit',
        'update' => 'condominiums.update',
        'destroy' => 'condominiums.destroy',
    ]);

    // Blocos (dentro do condomínio)
    Route::prefix('condominios/{condominium}')->name('condominiums.')->group(function () {
        Route::post('blocos', [CondominiumController::class, 'storeBlock'])->name('blocks.store');
        Route::patch('blocos/{block}', [CondominiumController::class, 'updateBlock'])->name('blocks.update');
        Route::delete('blocos/{block}', [CondominiumController::class, 'destroyBlock'])->name('blocks.destroy');

        Route::post('gestores', [CondominiumController::class, 'storeManager'])->name('managers.store');
        Route::delete('gestores/{manager}', [CondominiumController::class, 'destroyManager'])->name('managers.destroy');

        // Unidades
        Route::get('unidades', [UnitController::class, 'index'])->name('units.index');
        Route::get('unidades/criar', [UnitController::class, 'create'])->name('units.create');
        Route::post('unidades', [UnitController::class, 'store'])->name('units.store');
        Route::get('unidades/importar', [UnitController::class, 'importForm'])->name('units.import');
        Route::post('unidades/importar/preview', [UnitController::class, 'importPreview'])->name('units.import.preview');
        Route::post('unidades/importar/confirmar', [UnitController::class, 'importConfirm'])->name('units.import.confirm');
        Route::get('unidades/{unit}/editar', [UnitController::class, 'edit'])->name('units.edit');
        Route::patch('unidades/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('unidades/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });

    // Pessoas (busca por CPF antes do resource para não conflitar com {person})
    Route::get('pessoas/buscar-cpf', [PersonController::class, 'searchByCpf'])->name('persons.search-cpf');
    Route::resource('pessoas', PersonController::class)->parameters(['pessoas' => 'person'])->names([
        'index' => 'persons.index',
        'create' => 'persons.create',
        'store' => 'persons.store',
        'show' => 'persons.show',
        'edit' => 'persons.edit',
        'update' => 'persons.update',
        'destroy' => 'persons.destroy',
    ]);
    Route::post('pessoas/{person}/vinculos', [PersonController::class, 'storeLink'])->name('persons.links.store');
    Route::delete('pessoas/{person}/vinculos/{link}', [PersonController::class, 'destroyLink'])->name('persons.links.destroy');
});

require __DIR__.'/auth.php';
