<?php

use App\Http\Controllers\Portal\AnnouncementController;
use App\Http\Controllers\Portal\AssemblyController;
use App\Http\Controllers\Portal\ChargeController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentController;
use App\Http\Controllers\Portal\LostFoundController;
use App\Http\Controllers\Portal\OccurrenceController;
use App\Http\Controllers\Portal\ParcelController;
use App\Http\Controllers\Portal\PollController;
use App\Http\Controllers\Portal\ReservationController;
use App\Http\Controllers\Portal\UnitController;
use App\Http\Controllers\Portal\VisitorAuthorizationController;
use Illuminate\Support\Facades\Route;

// Portal do Morador — área dedicada, escopada à pessoa logada (middleware 'resident').
Route::middleware(['auth', 'verified', 'resident'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Comunicados (leitura + confirmação)
        Route::get('comunicados', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('comunicados/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

        // Ocorrências (próprias)
        Route::get('ocorrencias', [OccurrenceController::class, 'index'])->name('occurrences.index');
        Route::get('ocorrencias/criar', [OccurrenceController::class, 'create'])->name('occurrences.create');
        Route::post('ocorrencias', [OccurrenceController::class, 'store'])->name('occurrences.store');
        Route::get('ocorrencias/{occurrence}', [OccurrenceController::class, 'show'])->name('occurrences.show');
        Route::post('ocorrencias/{occurrence}/comentarios', [OccurrenceController::class, 'addComment'])->name('occurrences.comments.store');

        // Reservas (próprias)
        Route::get('reservas', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservas/criar', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('reservas', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservas/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])->name('reservations.cancel');

        // Documentos (públicos aos moradores)
        Route::get('documentos', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documentos/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

        // Minhas cobranças
        Route::middleware('module:financial')->group(function () {
            Route::get('cobrancas', [ChargeController::class, 'index'])->name('charges.index');
            Route::get('cobrancas/{charge}/comprovante', [ChargeController::class, 'download'])->name('charges.download');
            Route::post('cobrancas/{charge}/segunda-via', [ChargeController::class, 'secondCopy'])->name('charges.second-copy');
            Route::get('cobrancas/{charge}', [ChargeController::class, 'show'])->name('charges.show');
        });

        // Assembleias (presença + votação)
        Route::middleware('module:assemblies')->group(function () {
            Route::get('assembleias', [AssemblyController::class, 'index'])->name('assemblies.index');
            Route::get('assembleias/{assembly}', [AssemblyController::class, 'show'])->name('assemblies.show');
            Route::post('assembleias/{assembly}/presenca', [AssemblyController::class, 'attend'])->name('assemblies.attend');
            Route::post('assembleias/{assembly}/itens/{item}/voto', [AssemblyController::class, 'vote'])->name('assemblies.vote');
        });

        // Visitantes (pré-autorização com QR)
        Route::middleware('module:gatehouse')->group(function () {
            Route::get('visitantes', [VisitorAuthorizationController::class, 'index'])->name('visitors.index');
            Route::get('visitantes/criar', [VisitorAuthorizationController::class, 'create'])->name('visitors.create');
            Route::post('visitantes', [VisitorAuthorizationController::class, 'store'])->name('visitors.store');
            Route::get('visitantes/{authorization}', [VisitorAuthorizationController::class, 'show'])->name('visitors.show');
            Route::post('visitantes/{authorization}/revogar', [VisitorAuthorizationController::class, 'revoke'])->name('visitors.revoke');

            // Encomendas da minha unidade
            Route::get('encomendas', [ParcelController::class, 'index'])->name('parcels.index');
            Route::post('encomendas/{parcel}/retirada', [ParcelController::class, 'confirmPickup'])->name('parcels.pickup');
        });

        // Enquetes rápidas
        Route::middleware('module:polls')->group(function () {
            Route::get('enquetes', [PollController::class, 'index'])->name('polls.index');
            Route::get('enquetes/{poll}', [PollController::class, 'show'])->name('polls.show');
            Route::post('enquetes/{poll}/votar', [PollController::class, 'vote'])->name('polls.vote');
        });

        // Achados & Perdidos
        Route::middleware('module:lost_found')->group(function () {
            Route::get('achados-perdidos', [LostFoundController::class, 'index'])->name('lost-found.index');
            Route::get('achados-perdidos/criar', [LostFoundController::class, 'create'])->name('lost-found.create');
            Route::post('achados-perdidos', [LostFoundController::class, 'store'])->name('lost-found.store');
        });

        // Minha unidade
        Route::get('minha-unidade', [UnitController::class, 'show'])->name('unit.show');

        // Meu perfil
        Route::get('perfil', fn () => redirect()->route('profile.edit'))->name('profile.edit');
        Route::match(['put', 'patch'], 'perfil', fn () => redirect()->route('profile.edit'))->name('profile.update');
    });
