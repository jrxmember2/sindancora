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
    // Dashboard — acessível a qualquer usuário autenticado do tenant.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Usuários
    Route::middleware('permission:users:read')->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:users:create')->group(function () {
        Route::get('usuarios/create', [UserController::class, 'create'])->name('users.create');
        Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users:update')->group(function () {
        Route::get('usuarios/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::match(['put', 'patch'], 'usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::middleware('permission:users:delete')->group(function () {
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Perfis (roles) — gestão de perfis exige users:manage
    Route::middleware('permission:users:manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    // Auditoria
    Route::middleware('permission:audit:read')->group(function () {
        Route::get('/auditoria', [AuditController::class, 'index'])->name('audit.index');
    });

    // Condomínios — rotas estáticas (create) antes da dinâmica {condominium} (show).
    Route::middleware('permission:condominiums:read')->group(function () {
        Route::get('condominios', [CondominiumController::class, 'index'])->name('condominiums.index');
    });
    Route::middleware('permission:condominiums:create')->group(function () {
        Route::get('condominios/create', [CondominiumController::class, 'create'])->name('condominiums.create');
        Route::post('condominios', [CondominiumController::class, 'store'])->name('condominiums.store');
    });
    Route::middleware('permission:condominiums:update')->group(function () {
        Route::get('condominios/{condominium}/edit', [CondominiumController::class, 'edit'])->name('condominiums.edit');
        Route::match(['put', 'patch'], 'condominios/{condominium}', [CondominiumController::class, 'update'])->name('condominiums.update');
    });
    Route::middleware('permission:condominiums:delete')->group(function () {
        Route::delete('condominios/{condominium}', [CondominiumController::class, 'destroy'])->name('condominiums.destroy');
    });

    // Blocos e gestores (estrutura do condomínio → condominiums:update)
    Route::middleware('permission:condominiums:update')->prefix('condominios/{condominium}')->name('condominiums.')->group(function () {
        Route::post('blocos', [CondominiumController::class, 'storeBlock'])->name('blocks.store');
        Route::patch('blocos/{block}', [CondominiumController::class, 'updateBlock'])->name('blocks.update');
        Route::delete('blocos/{block}', [CondominiumController::class, 'destroyBlock'])->name('blocks.destroy');

        Route::post('gestores', [CondominiumController::class, 'storeManager'])->name('managers.store');
        Route::delete('gestores/{manager}', [CondominiumController::class, 'destroyManager'])->name('managers.destroy');
    });

    // Unidades (dentro do condomínio)
    Route::prefix('condominios/{condominium}')->name('condominiums.')->group(function () {
        Route::middleware('permission:units:import')->group(function () {
            Route::get('unidades/importar', [UnitController::class, 'importForm'])->name('units.import');
            Route::post('unidades/importar/preview', [UnitController::class, 'importPreview'])->name('units.import.preview');
            Route::post('unidades/importar/confirmar', [UnitController::class, 'importConfirm'])->name('units.import.confirm');
        });
        Route::middleware('permission:units:create')->group(function () {
            Route::get('unidades/criar', [UnitController::class, 'create'])->name('units.create');
            Route::post('unidades', [UnitController::class, 'store'])->name('units.store');
        });
        Route::middleware('permission:units:read')->group(function () {
            Route::get('unidades', [UnitController::class, 'index'])->name('units.index');
        });
        Route::middleware('permission:units:update')->group(function () {
            Route::get('unidades/{unit}/editar', [UnitController::class, 'edit'])->name('units.edit');
            Route::patch('unidades/{unit}', [UnitController::class, 'update'])->name('units.update');
        });
        Route::middleware('permission:units:delete')->group(function () {
            Route::delete('unidades/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
        });
    });

    // Condomínio — detalhe (dinâmica, registrada após as estáticas acima)
    Route::middleware('permission:condominiums:read')->group(function () {
        Route::get('condominios/{condominium}', [CondominiumController::class, 'show'])->name('condominiums.show');
    });

    // Pessoas — rotas estáticas (buscar-cpf, create) antes da dinâmica {person} (show).
    Route::middleware('permission:persons:read')->group(function () {
        Route::get('pessoas/buscar-cpf', [PersonController::class, 'searchByCpf'])->name('persons.search-cpf');
        Route::get('pessoas', [PersonController::class, 'index'])->name('persons.index');
    });
    Route::middleware('permission:persons:create')->group(function () {
        Route::get('pessoas/create', [PersonController::class, 'create'])->name('persons.create');
        Route::post('pessoas', [PersonController::class, 'store'])->name('persons.store');
    });
    Route::middleware('permission:persons:update')->group(function () {
        Route::get('pessoas/{person}/edit', [PersonController::class, 'edit'])->name('persons.edit');
        Route::match(['put', 'patch'], 'pessoas/{person}', [PersonController::class, 'update'])->name('persons.update');
    });
    Route::middleware('permission:persons:delete')->group(function () {
        Route::delete('pessoas/{person}', [PersonController::class, 'destroy'])->name('persons.destroy');
    });
    Route::middleware('permission:persons:link')->group(function () {
        Route::post('pessoas/{person}/vinculos', [PersonController::class, 'storeLink'])->name('persons.links.store');
        Route::delete('pessoas/{person}/vinculos/{link}', [PersonController::class, 'destroyLink'])->name('persons.links.destroy');
    });
    // Pessoa — detalhe (dinâmica, registrada após as estáticas acima)
    Route::middleware('permission:persons:read')->group(function () {
        Route::get('pessoas/{person}', [PersonController::class, 'show'])->name('persons.show');
    });
});

require __DIR__.'/auth.php';
