<?php

use App\Http\Controllers\Portaria\PortariaController;
use Illuminate\Support\Facades\Route;

// Área da Portaria — porteiros e gestores (middleware 'gatehouse'). Tela minimalista
// para registrar acessos e validar QR/token de visitantes.
Route::middleware(['auth', 'verified', 'gatehouse'])
    ->prefix('portaria')
    ->name('portaria.')
    ->group(function () {
        Route::get('/', [PortariaController::class, 'index'])->name('index');

        // Validação de QR/token
        Route::get('validar', [PortariaController::class, 'validateForm'])->name('validate');
        Route::post('validar', [PortariaController::class, 'validateToken'])->name('validate.check');

        // Registro de acessos
        Route::post('entrada/autorizada', [PortariaController::class, 'checkInAuthorized'])->name('checkin.authorized');
        Route::post('entrada/avulsa', [PortariaController::class, 'checkInWalkIn'])->name('checkin.walkin');
        Route::post('visitas/{visit}/saida', [PortariaController::class, 'checkOut'])->name('checkout');

        // Histórico de acessos
        Route::get('visitas', [PortariaController::class, 'log'])->name('log');
    });
