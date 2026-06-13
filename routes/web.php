<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BillingBlockController;
use App\Http\Controllers\FirstAccessController;
use App\Http\Controllers\OAuth\GoogleDriveCallbackController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicIntakeController;
use App\Http\Controllers\Panel\DriveIntegrationController;
use App\Http\Controllers\Panel\AnnouncementController;
use App\Http\Controllers\Panel\ApiKeyController;
use App\Http\Controllers\Panel\AssemblyController;
use App\Http\Controllers\Panel\AssistantController;
use App\Http\Controllers\Panel\AuditController;
use App\Http\Controllers\Panel\CampaignController;
use App\Http\Controllers\Panel\CategoryController;
use App\Http\Controllers\Panel\ChargeController;
use App\Http\Controllers\Panel\CommonAreaController;
use App\Http\Controllers\Panel\CommunityPostController;
use App\Http\Controllers\Panel\CondominiumController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\DisciplinaryRecordController;
use App\Http\Controllers\Panel\DocumentController;
use App\Http\Controllers\Panel\EmployeeController;
use App\Http\Controllers\Panel\ExpenseController;
use App\Http\Controllers\Panel\GatehouseController;
use App\Http\Controllers\Panel\InboxController;
use App\Http\Controllers\Panel\LostFoundController;
use App\Http\Controllers\Panel\MailSettingController;
use App\Http\Controllers\Panel\NotificationController;
use App\Http\Controllers\Panel\MaintenanceController;
use App\Http\Controllers\Panel\OccurrenceController;
use App\Http\Controllers\Panel\OccurrenceSlaController;
use App\Http\Controllers\Panel\ParcelController;
use App\Http\Controllers\Panel\PollController;
use App\Http\Controllers\Panel\QuotationController;
use App\Http\Controllers\Panel\SupplierController;
use App\Http\Controllers\Panel\PaymentSettingController;
use App\Http\Controllers\Panel\PersonController;
use App\Http\Controllers\Panel\PublicLinkController;
use App\Http\Controllers\Panel\PublicSubmissionController;
use App\Http\Controllers\Panel\QuickReplyController;
use App\Http\Controllers\Panel\ReportController;
use App\Http\Controllers\Panel\ReservationController;
use App\Http\Controllers\Panel\RoleController;
use App\Http\Controllers\Panel\ScheduleController;
use App\Http\Controllers\Panel\SectorController;
use App\Http\Controllers\Panel\TenantProfileController;
use App\Http\Controllers\Panel\UnitController;
use App\Http\Controllers\Panel\UserController;
use App\Http\Controllers\Panel\WebhookController;
use App\Http\Controllers\Panel\WorkController;
use App\Http\Controllers\Panel\WhatsappBotController;
use App\Http\Controllers\Panel\WhatsappReportController;
use App\Http\Controllers\Panel\WhatsappConnectionController;
use App\Http\Controllers\Panel\WhatsappSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Onboarding antigo: criava o tenant na hora, sem pagamento. Agora o caminho oficial é o checkout
// (planos → Asaas → provisionamento via webhook). Mantido como redirect para não quebrar links.
Route::get('/cadastro', fn () => redirect()->route('checkout.plans'))->name('onboarding.create');

// Site público de contratação (domínio apex, sem tenant — liberado no ResolveTenant).
Route::get('/planos', [CheckoutController::class, 'plans'])->name('checkout.plans');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/checkout/{signup}/pendente', [CheckoutController::class, 'pending'])->name('checkout.pending');
Route::get('/checkout/{signup}/status', [CheckoutController::class, 'status'])->name('checkout.status');

// Primeiro acesso por link mágico (assinado) enviado no e-mail de boas-vindas — domínio do tenant.
Route::get('/primeiro-acesso/{user}', [FirstAccessController::class, 'login'])->name('first-access.login');

// Tela de bloqueio do tenant suspenso (acessível mesmo bloqueado; ver ResolveTenant).
Route::get('/assinatura-em-atraso', [BillingBlockController::class, 'show'])->name('billing.suspended');

// Intake público por link/QR do condomínio (sem login). Tenant resolvido pelo domínio;
// o token é escopado ao tenant. Tudo entra como envio pendente de moderação.
Route::prefix('p/{token}')->name('public.intake.')->group(function () {
    Route::get('/', [PublicIntakeController::class, 'landing'])->name('landing');
    Route::get('enviado', [PublicIntakeController::class, 'sent'])->name('sent');

    Route::get('morador', [PublicIntakeController::class, 'residentForm'])->name('resident');
    Route::get('ocorrencia', [PublicIntakeController::class, 'occurrenceForm'])->name('occurrence');
    Route::get('status', [PublicIntakeController::class, 'statusForm'])->name('status');

    // Envios e consulta pública: throttle anti-abuso por IP.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('morador', [PublicIntakeController::class, 'residentStore'])->name('resident.store');
        Route::post('ocorrencia', [PublicIntakeController::class, 'occurrenceStore'])->name('occurrence.store');
        Route::post('status', [PublicIntakeController::class, 'statusCheck'])->name('status.check');
    });
});

// Callback central do OAuth do Google Drive (redirect_uri único). Fora do ResolveTenant e sem auth:
// o tenant é identificado pelo `state` assinado e o usuário é devolvido ao domínio do tenant.
Route::get('oauth/google-drive/callback', GoogleDriveCallbackController::class)->name('oauth.google-drive.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    // Perfil do usuario - painel, portal e superadmin.
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/perfil/foto', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/perfil/foto', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::get('/perfil/foto', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::patch('/perfil/notificacoes', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');

    // Notificações in-app — disponíveis a qualquer usuário autenticado (painel e portal).
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/marcar-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notificacoes/{id}/lida', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Anexos genéricos (comunicados, ocorrências, áreas) — painel e portal; acesso resolvido por entity_type.
    Route::get('/anexos/{object}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/anexos/{object}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Painel administrativo — bloqueado para moradores "puros" (redirecionados ao portal).
    Route::middleware('panel')->group(function () {
    // Dashboard modular (metadados + widgets não-lazy na página; lazy/refresh via JSON)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/widgets/{key}', [DashboardController::class, 'widget'])->name('dashboard.widget');
    Route::put('/dashboard/preferences', [DashboardController::class, 'preferences'])->name('dashboard.preferences');

    // Cronograma consolidado
    Route::middleware('permission:schedule:read')->group(function () {
        Route::get('cronograma', [ScheduleController::class, 'index'])->name('schedule.index');
    });

    // Links públicos + QR por condomínio (auto-cadastro / ocorrência com moderação)
    Route::middleware('module:public_links')->prefix('links-publicos')->name('public-links.')->group(function () {
        Route::middleware('permission:public_links:read')->group(function () {
            Route::get('/', [PublicLinkController::class, 'index'])->name('index');

            // Moderação (rotas estáticas antes do parâmetro {condominium}).
            Route::get('moderacao', [PublicSubmissionController::class, 'index'])->name('moderation.index');
            Route::get('moderacao/{submission}', [PublicSubmissionController::class, 'show'])->name('moderation.show');
        });

        Route::middleware('permission:public_links:manage')->group(function () {
            Route::post('moderacao/{submission}/aprovar', [PublicSubmissionController::class, 'approve'])->name('moderation.approve');
            Route::post('moderacao/{submission}/reprovar', [PublicSubmissionController::class, 'reject'])->name('moderation.reject');

            Route::post('{condominium}/gerar', [PublicLinkController::class, 'generate'])->name('generate');
            Route::match(['put', 'patch'], '{condominium}', [PublicLinkController::class, 'update'])->name('update');
        });
    });

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
        Route::get('condominios/{condominium}/logo', [CondominiumController::class, 'logo'])->name('condominiums.logo');
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
        Route::post('pessoas/{person}/convidar', [PersonController::class, 'invite'])->name('persons.invite');
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

    // Funcionarios e controle de ferias.
    Route::middleware('permission:employees:create')->group(function () {
        Route::get('funcionarios/criar', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('funcionarios', [EmployeeController::class, 'store'])->name('employees.store');
    });
    Route::middleware('permission:employees:update')->group(function () {
        Route::get('funcionarios/{employee}/editar', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::match(['put', 'patch'], 'funcionarios/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('funcionarios/{employee}/ferias', [EmployeeController::class, 'storeVacationPeriod'])->name('employees.vacations.store');
        Route::match(['put', 'patch'], 'funcionarios/ferias/{period}', [EmployeeController::class, 'updateVacationPeriod'])->name('employees.vacations.update');
    });
    Route::middleware('permission:employees:delete')->group(function () {
        Route::delete('funcionarios/ferias/{period}', [EmployeeController::class, 'destroyVacationPeriod'])->name('employees.vacations.destroy');
        Route::delete('funcionarios/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });
    Route::middleware('permission:employees:read')->group(function () {
        Route::get('funcionarios', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('funcionarios/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });

    // Comunicados — rotas estáticas (create) antes da dinâmica {announcement} (show).
    Route::middleware('permission:announcements:read')->group(function () {
        Route::get('comunicados', [AnnouncementController::class, 'index'])->name('announcements.index');
    });
    Route::middleware('permission:announcements:create')->group(function () {
        Route::get('comunicados/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('comunicados', [AnnouncementController::class, 'store'])->name('announcements.store');
    });
    Route::middleware('permission:announcements:update')->group(function () {
        Route::get('comunicados/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::match(['put', 'patch'], 'comunicados/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    });
    Route::middleware('permission:announcements:publish')->group(function () {
        Route::post('comunicados/{announcement}/publicar', [AnnouncementController::class, 'publish'])->name('announcements.publish');
    });
    Route::middleware('permission:announcements:delete')->group(function () {
        Route::delete('comunicados/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });
    // Comunicado — detalhe (dinâmica, registrada após as estáticas acima)
    Route::middleware('permission:announcements:read')->group(function () {
        Route::get('comunicados/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    });

    // Ocorrências — rotas estáticas (create, painel) antes da dinâmica {occurrence} (show).
    Route::middleware('permission:occurrences:read')->group(function () {
        Route::get('ocorrencias', [OccurrenceController::class, 'index'])->name('occurrences.index');
        Route::get('ocorrencias/painel', [OccurrenceController::class, 'dashboard'])->name('occurrences.dashboard');
    });
    Route::middleware('permission:occurrences:create')->group(function () {
        Route::get('ocorrencias/create', [OccurrenceController::class, 'create'])->name('occurrences.create');
        Route::post('ocorrencias', [OccurrenceController::class, 'store'])->name('occurrences.store');
    });
    Route::middleware('permission:occurrences:update')->group(function () {
        Route::get('ocorrencias/{occurrence}/edit', [OccurrenceController::class, 'edit'])->name('occurrences.edit');
        Route::match(['put', 'patch'], 'ocorrencias/{occurrence}', [OccurrenceController::class, 'update'])->name('occurrences.update');
        Route::post('ocorrencias/{occurrence}/status', [OccurrenceController::class, 'changeStatus'])->name('occurrences.status');
        Route::post('ocorrencias/{occurrence}/responsavel', [OccurrenceController::class, 'assign'])->name('occurrences.assign');
        Route::post('ocorrencias/{occurrence}/comentarios', [OccurrenceController::class, 'addComment'])->name('occurrences.comments.store');
    });
    Route::middleware('permission:occurrences:delete')->group(function () {
        Route::delete('ocorrencias/{occurrence}', [OccurrenceController::class, 'destroy'])->name('occurrences.destroy');
    });
    // Sugestão de resposta por IA (precisa da permissão de IA)
    Route::middleware('permission:ai:use')->group(function () {
        Route::post('ocorrencias/{occurrence}/sugestao-ia', [OccurrenceController::class, 'draftReply'])->name('occurrences.draft-reply');
    });
    // Ocorrência — detalhe (dinâmica, registrada após as estáticas acima)
    Route::middleware('permission:occurrences:read')->group(function () {
        Route::get('ocorrencias/{occurrence}', [OccurrenceController::class, 'show'])->name('occurrences.show');
    });

    // Documentos — rotas estáticas (create) antes das dinâmicas {document}.
    Route::middleware('permission:documents:read')->group(function () {
        Route::get('documentos', [DocumentController::class, 'index'])->name('documents.index');
    });
    Route::middleware('permission:documents:upload')->group(function () {
        Route::get('documentos/enviar', [DocumentController::class, 'create'])->name('documents.create');
        Route::post('documentos', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documentos/{document}/editar', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::match(['put', 'patch'], 'documentos/{document}', [DocumentController::class, 'update'])->name('documents.update');
    });
    Route::middleware('permission:documents:download')->group(function () {
        Route::get('documentos/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    });
    Route::middleware('permission:documents:delete')->group(function () {
        Route::delete('documentos/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Reservas — rotas estáticas (areas, criar) antes da dinâmica {reservation} (show).
    Route::middleware('permission:reservations:read')->group(function () {
        Route::get('reservas', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservas/areas', [CommonAreaController::class, 'index'])->name('areas.index');
    });
    // Gestão de áreas comuns — nível administrativo (quem aprova reservas).
    Route::middleware('permission:reservations:approve')->group(function () {
        Route::get('reservas/areas/criar', [CommonAreaController::class, 'create'])->name('areas.create');
        Route::post('reservas/areas', [CommonAreaController::class, 'store'])->name('areas.store');
        Route::get('reservas/areas/{area}/editar', [CommonAreaController::class, 'edit'])->name('areas.edit');
        Route::match(['put', 'patch'], 'reservas/areas/{area}', [CommonAreaController::class, 'update'])->name('areas.update');
        Route::delete('reservas/areas/{area}', [CommonAreaController::class, 'destroy'])->name('areas.destroy');
    });
    Route::middleware('permission:reservations:create')->group(function () {
        Route::get('reservas/criar', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('reservas', [ReservationController::class, 'store'])->name('reservations.store');
    });
    Route::middleware('permission:reservations:approve')->group(function () {
        Route::post('reservas/{reservation}/aprovar', [ReservationController::class, 'approve'])->name('reservations.approve');
    });
    Route::middleware('permission:reservations:reject')->group(function () {
        Route::post('reservas/{reservation}/recusar', [ReservationController::class, 'reject'])->name('reservations.reject');
    });
    Route::middleware('permission:reservations:cancel')->group(function () {
        Route::post('reservas/{reservation}/cancelar', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });
    // Reserva — detalhe (dinâmica, registrada após as estáticas acima)
    Route::middleware('permission:reservations:read')->group(function () {
        Route::get('reservas/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    });

    // Fornecedores/prestadores — estáticas (criar) antes da dinâmica {supplier}.
    Route::middleware('permission:suppliers:create')->group(function () {
        Route::get('fornecedores/criar', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('fornecedores', [SupplierController::class, 'store'])->name('suppliers.store');
    });
    Route::middleware('permission:suppliers:update')->group(function () {
        Route::get('fornecedores/{supplier}/editar', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::match(['put', 'patch'], 'fornecedores/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('fornecedores/{supplier}/avaliacoes', [SupplierController::class, 'storeEvaluation'])->name('suppliers.evaluations.store');
    });
    Route::middleware('permission:suppliers:delete')->group(function () {
        Route::delete('fornecedores/avaliacoes/{evaluation}', [SupplierController::class, 'destroyEvaluation'])->name('suppliers.evaluations.destroy');
        Route::delete('fornecedores/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });
    Route::middleware('permission:suppliers:read')->group(function () {
        Route::get('fornecedores', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('fornecedores/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    });

    // Manutenção preventiva — estáticas (criar) antes da dinâmica {maintenance}.
    Route::middleware('permission:maintenance:create')->group(function () {
        Route::get('manutencoes/criar', [MaintenanceController::class, 'create'])->name('maintenance.create');
        Route::post('manutencoes', [MaintenanceController::class, 'store'])->name('maintenance.store');
    });
    Route::middleware('permission:maintenance:update')->group(function () {
        Route::get('manutencoes/{maintenance}/editar', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
        Route::match(['put', 'patch'], 'manutencoes/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::post('manutencoes/{maintenance}/execucoes', [MaintenanceController::class, 'registerExecution'])->name('maintenance.executions.store');
    });
    Route::middleware('permission:maintenance:delete')->group(function () {
        Route::delete('manutencoes/execucoes/{record}', [MaintenanceController::class, 'destroyRecord'])->name('maintenance.executions.destroy');
        Route::delete('manutencoes/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
    });
    Route::middleware('permission:maintenance:read')->group(function () {
        Route::get('manutencoes', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::get('manutencoes/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    });

    // Orçamentos/Cotações — propostas multi-fornecedor e aprovação.
    Route::middleware('permission:quotations:create')->group(function () {
        Route::get('orcamentos/criar', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('orcamentos', [QuotationController::class, 'store'])->name('quotations.store');
    });
    Route::middleware('permission:quotations:update')->group(function () {
        Route::get('orcamentos/{quotation}/editar', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::match(['put', 'patch'], 'orcamentos/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::post('orcamentos/{quotation}/propostas', [QuotationController::class, 'storeProposal'])->name('quotations.proposals.store');
        Route::delete('orcamentos/propostas/{proposal}', [QuotationController::class, 'destroyProposal'])->name('quotations.proposals.destroy');
    });
    Route::middleware('permission:quotations:approve')->group(function () {
        Route::post('orcamentos/propostas/{proposal}/aprovar', [QuotationController::class, 'approveProposal'])->name('quotations.proposals.approve');
        Route::post('orcamentos/{quotation}/reprovar', [QuotationController::class, 'reject'])->name('quotations.reject');
    });
    Route::middleware('permission:quotations:delete')->group(function () {
        Route::delete('orcamentos/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
    });
    Route::middleware('permission:quotations:read')->group(function () {
        Route::get('orcamentos', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('orcamentos/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    });

    // Obras/Reformas — acompanhamento operacional com anexos, andamentos e contas vinculadas.
    Route::middleware('permission:works:create')->group(function () {
        Route::get('obras/criar', [WorkController::class, 'create'])->name('works.create');
        Route::post('obras', [WorkController::class, 'store'])->name('works.store');
    });
    Route::middleware('permission:works:update')->group(function () {
        Route::get('obras/{work}/editar', [WorkController::class, 'edit'])->name('works.edit');
        Route::match(['put', 'patch'], 'obras/{work}', [WorkController::class, 'update'])->name('works.update');
        Route::post('obras/{work}/andamentos', [WorkController::class, 'storeUpdate'])->name('works.updates.store');
        Route::post('obras/{work}/contas-a-pagar', [WorkController::class, 'storeExpense'])->name('works.expenses.store');
    });
    Route::middleware('permission:works:delete')->group(function () {
        Route::delete('obras/{work}', [WorkController::class, 'destroy'])->name('works.destroy');
    });
    Route::middleware('permission:works:read')->group(function () {
        Route::get('obras', [WorkController::class, 'index'])->name('works.index');
        Route::get('obras/{work}', [WorkController::class, 'show'])->name('works.show');
    });

    // Cobranças — rotas estáticas (criar, gerar) antes da dinâmica {charge}.
    Route::middleware('permission:charges:read')->group(function () {
        Route::get('cobrancas', [ChargeController::class, 'index'])->name('charges.index');
    });
    Route::middleware('permission:charges:create')->group(function () {
        Route::get('cobrancas/criar', [ChargeController::class, 'create'])->name('charges.create');
        Route::post('cobrancas', [ChargeController::class, 'store'])->name('charges.store');
        Route::get('cobrancas/gerar', [ChargeController::class, 'generateForm'])->name('charges.generate');
        Route::post('cobrancas/gerar/preview', [ChargeController::class, 'generatePreview'])->name('charges.generate.preview');
        Route::post('cobrancas/gerar/confirmar', [ChargeController::class, 'generateConfirm'])->name('charges.generate.confirm');
    });
    Route::middleware('permission:charges:update')->group(function () {
        Route::get('cobrancas/{charge}/editar', [ChargeController::class, 'edit'])->name('charges.edit');
        Route::match(['put', 'patch'], 'cobrancas/{charge}', [ChargeController::class, 'update'])->name('charges.update');
        Route::post('cobrancas/{charge}/boleto', [ChargeController::class, 'issueGateway'])->name('charges.issue');
        Route::post('cobrancas/{charge}/segunda-via', [ChargeController::class, 'secondCopy'])->name('charges.second-copy');
    });
    Route::middleware('permission:charges:mark_paid')->group(function () {
        Route::post('cobrancas/{charge}/pagar', [ChargeController::class, 'registerPayment'])->name('charges.pay');
    });
    Route::middleware('permission:charges:delete')->group(function () {
        Route::delete('cobrancas/{charge}', [ChargeController::class, 'destroy'])->name('charges.destroy');
    });
    Route::middleware('permission:charges:read')->group(function () {
        Route::get('cobrancas/{charge}/comprovante', [ChargeController::class, 'download'])->name('charges.download');
        Route::get('cobrancas/{charge}', [ChargeController::class, 'show'])->name('charges.show');
    });

    // Despesas — estáticas (criar) antes da dinâmica {expense}.
    Route::middleware('permission:expenses:read')->group(function () {
        Route::get('despesas', [ExpenseController::class, 'index'])->name('expenses.index');
    });
    Route::middleware('permission:expenses:create')->group(function () {
        Route::get('despesas/criar', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('despesas', [ExpenseController::class, 'store'])->name('expenses.store');
    });
    Route::middleware('permission:expenses:update')->group(function () {
        Route::get('despesas/{expense}/editar', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::match(['put', 'patch'], 'despesas/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::post('despesas/{expense}/pagar', [ExpenseController::class, 'markPaid'])->name('expenses.pay');
    });
    Route::middleware('permission:expenses:delete')->group(function () {
        Route::delete('despesas/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });
    Route::middleware('permission:expenses:read')->group(function () {
        Route::get('despesas/{expense}/comprovante', [ExpenseController::class, 'download'])->name('expenses.download');
    });

    // Relatórios financeiros
    Route::middleware('permission:reports:read')->group(function () {
        Route::get('relatorios', [ReportController::class, 'index'])->name('reports.index');
    });
    Route::middleware('permission:reports:export')->group(function () {
        Route::get('relatorios/financeiro/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');
        Route::get('relatorios/financeiro/xlsx', [ReportController::class, 'exportXlsx'])->name('reports.xlsx');
    });

    // Configurações > Dados do tenant (marca e cadastro para relatórios)
    Route::middleware('permission:settings:update')->group(function () {
        Route::get('configuracoes/tenant', [TenantProfileController::class, 'edit'])->name('settings.tenant.edit');
        Route::post('configuracoes/tenant', [TenantProfileController::class, 'update'])->name('settings.tenant.update');

        // Configurações > Armazenamento (Google Drive externo para mídia de WhatsApp)
        Route::get('configuracoes/armazenamento', [DriveIntegrationController::class, 'show'])->name('settings.storage.show');
        Route::get('configuracoes/armazenamento/conectar', [DriveIntegrationController::class, 'connect'])->name('settings.storage.connect');
        Route::delete('configuracoes/armazenamento', [DriveIntegrationController::class, 'disconnect'])->name('settings.storage.disconnect');
        Route::match(['put', 'patch'], 'configuracoes/armazenamento/limpeza', [DriveIntegrationController::class, 'updateCleanup'])->name('settings.storage.cleanup');
        Route::post('configuracoes/armazenamento/liberar', [DriveIntegrationController::class, 'freeSpace'])->name('settings.storage.free');
    });

    // Configurações > Pagamentos (integração Asaas por tenant)
    Route::middleware('permission:settings:payments')->group(function () {
        Route::get('configuracoes/pagamentos', [PaymentSettingController::class, 'edit'])->name('settings.payments.edit');
        Route::match(['put', 'patch'], 'configuracoes/pagamentos', [PaymentSettingController::class, 'update'])->name('settings.payments.update');
        Route::post('configuracoes/pagamentos/testar', [PaymentSettingController::class, 'test'])->name('settings.payments.test');
    });

    // Configurações > API (chaves de API por tenant)
    Route::middleware('permission:api_keys:manage')->group(function () {
        Route::get('configuracoes/api', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('configuracoes/api', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('configuracoes/api/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
    });

    // Configurações > Webhooks de saída
    Route::middleware('permission:webhooks:manage')->group(function () {
        Route::get('configuracoes/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('configuracoes/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::match(['put', 'patch'], 'configuracoes/webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
        Route::post('configuracoes/webhooks/{webhook}/testar', [WebhookController::class, 'test'])->name('webhooks.test');
        Route::delete('configuracoes/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    });

    // Assembleias digitais — estáticas (criar) antes da dinâmica {assembly}.
    Route::middleware('permission:assemblies:read')->group(function () {
        Route::get('assembleias', [AssemblyController::class, 'index'])->name('assemblies.index');
    });
    Route::middleware('permission:assemblies:create')->group(function () {
        Route::get('assembleias/criar', [AssemblyController::class, 'create'])->name('assemblies.create');
        Route::post('assembleias', [AssemblyController::class, 'store'])->name('assemblies.store');
    });
    Route::middleware('permission:assemblies:update')->group(function () {
        Route::get('assembleias/{assembly}/editar', [AssemblyController::class, 'edit'])->name('assemblies.edit');
        Route::match(['put', 'patch'], 'assembleias/{assembly}', [AssemblyController::class, 'update'])->name('assemblies.update');
        Route::post('assembleias/{assembly}/itens', [AssemblyController::class, 'storeItem'])->name('assemblies.items.store');
        Route::delete('assembleias/{assembly}/itens/{item}', [AssemblyController::class, 'destroyItem'])->name('assemblies.items.destroy');
        Route::post('assembleias/{assembly}/abrir', [AssemblyController::class, 'open'])->name('assemblies.open');
        Route::post('assembleias/{assembly}/encerrar', [AssemblyController::class, 'close'])->name('assemblies.close');
        Route::post('assembleias/{assembly}/ata', [AssemblyController::class, 'generateMinutes'])->name('assemblies.minutes');
    });
    Route::middleware('permission:assemblies:delete')->group(function () {
        Route::delete('assembleias/{assembly}', [AssemblyController::class, 'destroy'])->name('assemblies.destroy');
    });
    Route::middleware('permission:assemblies:read')->group(function () {
        Route::get('assembleias/{assembly}/ata/pdf', [AssemblyController::class, 'downloadMinutes'])->name('assemblies.minutes.pdf');
        Route::get('assembleias/{assembly}', [AssemblyController::class, 'show'])->name('assemblies.show');
    });

    // Portaria — monitoramento e autorizações (telas do porteiro ficam em /portaria).
    Route::middleware('permission:gatehouse:read')->group(function () {
        Route::get('visitantes', [GatehouseController::class, 'index'])->name('gatehouse.index');
    });
    Route::middleware('permission:gatehouse:manage')->group(function () {
        Route::post('visitantes/autorizacoes', [GatehouseController::class, 'storeAuthorization'])->name('gatehouse.authorizations.store');
        Route::delete('visitantes/autorizacoes/{authorization}', [GatehouseController::class, 'revokeAuthorization'])->name('gatehouse.authorizations.revoke');
    });

    // Encomendas — acompanhamento/baixa pelo gestor (o registro fica na área da portaria).
    Route::middleware('module:gatehouse')->group(function () {
        Route::middleware('permission:gatehouse:read')->group(function () {
            Route::get('encomendas', [ParcelController::class, 'index'])->name('parcels.index');
        });
        Route::middleware('permission:gatehouse:manage')->group(function () {
            Route::post('encomendas/{parcel}/retirada', [ParcelController::class, 'markPickedUp'])->name('parcels.pickup');
        });
    });

    // Enquetes rápidas
    Route::middleware('module:polls')->group(function () {
        Route::middleware('permission:polls:manage')->group(function () {
            Route::get('enquetes/criar', [PollController::class, 'create'])->name('polls.create');
            Route::post('enquetes', [PollController::class, 'store'])->name('polls.store');
            Route::post('enquetes/{poll}/abrir', [PollController::class, 'open'])->name('polls.open');
            Route::post('enquetes/{poll}/encerrar', [PollController::class, 'close'])->name('polls.close');
            Route::delete('enquetes/{poll}', [PollController::class, 'destroy'])->name('polls.destroy');
        });
        Route::middleware('permission:polls:read')->group(function () {
            Route::get('enquetes', [PollController::class, 'index'])->name('polls.index');
            Route::get('enquetes/{poll}', [PollController::class, 'show'])->name('polls.show');
        });
    });

    // Achados & Perdidos
    Route::middleware('module:lost_found')->group(function () {
        Route::middleware('permission:lost_found:manage')->group(function () {
            Route::get('achados-perdidos/criar', [LostFoundController::class, 'create'])->name('lost-found.create');
            Route::post('achados-perdidos', [LostFoundController::class, 'store'])->name('lost-found.store');
            Route::post('achados-perdidos/{item}/resolver', [LostFoundController::class, 'resolve'])->name('lost-found.resolve');
            Route::delete('achados-perdidos/{item}', [LostFoundController::class, 'destroy'])->name('lost-found.destroy');
        });
        Route::middleware('permission:lost_found:read')->group(function () {
            Route::get('achados-perdidos', [LostFoundController::class, 'index'])->name('lost-found.index');
        });
    });

    // Multas e advertencias regimentais
    Route::middleware('module:disciplinary')->prefix('multas-advertencias')->name('disciplinary.')->group(function () {
        Route::middleware('permission:disciplinary:manage')->group(function () {
            Route::get('criar', [DisciplinaryRecordController::class, 'create'])->name('create');
            Route::post('/', [DisciplinaryRecordController::class, 'store'])->name('store');
            Route::post('{record}/cobranca', [DisciplinaryRecordController::class, 'generateCharge'])->name('charge');
            Route::post('{record}/cancelar', [DisciplinaryRecordController::class, 'cancel'])->name('cancel');
        });
        Route::middleware('permission:disciplinary:read')->group(function () {
            Route::get('/', [DisciplinaryRecordController::class, 'index'])->name('index');
            Route::get('{record}', [DisciplinaryRecordController::class, 'show'])->name('show');
        });
    });

    // Mural e classificados
    Route::middleware('module:community_board')->prefix('mural')->name('community-board.')->group(function () {
        Route::middleware('permission:community_board:manage')->group(function () {
            Route::get('criar', [CommunityPostController::class, 'create'])->name('create');
            Route::post('/', [CommunityPostController::class, 'store'])->name('store');
            Route::post('{post}/aprovar', [CommunityPostController::class, 'approve'])->name('approve');
            Route::post('{post}/rejeitar', [CommunityPostController::class, 'reject'])->name('reject');
            Route::post('{post}/arquivar', [CommunityPostController::class, 'archive'])->name('archive');
            Route::delete('{post}', [CommunityPostController::class, 'destroy'])->name('destroy');
        });
        Route::middleware('permission:community_board:read')->group(function () {
            Route::get('/', [CommunityPostController::class, 'index'])->name('index');
        });
    });

    // Assistente de IA
    Route::middleware('permission:ai:use')->group(function () {
        Route::get('assistente', [AssistantController::class, 'index'])->name('assistant.index');
        Route::post('assistente/mensagem', [AssistantController::class, 'message'])->name('assistant.message');
        Route::post('assistente/inadimplencia', [AssistantController::class, 'delinquency'])->name('assistant.delinquency');
        Route::post('assistente/comunicado', [AssistantController::class, 'announcement'])->name('assistant.announcement');
        Route::delete('assistente/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.destroy');
    });

    // Configurações > E-mail (SMTP/IMAP do tenant).
    Route::middleware('permission:settings:email')->group(function () {
        Route::get('configuracoes/email', [MailSettingController::class, 'edit'])->name('settings.email.edit');
        Route::match(['put', 'patch'], 'configuracoes/email', [MailSettingController::class, 'update'])->name('settings.email.update');
        Route::post('configuracoes/email/testar', [MailSettingController::class, 'test'])->name('settings.email.test');
    });

    // Configurações > SLA de chamados (dias por prioridade)
    Route::middleware('permission:occurrences:update')->group(function () {
        Route::get('configuracoes/ocorrencias', [OccurrenceSlaController::class, 'edit'])->name('settings.occurrence-sla.edit');
        Route::match(['put', 'patch'], 'configuracoes/ocorrencias', [OccurrenceSlaController::class, 'update'])->name('settings.occurrence-sla.update');
    });

    // Categorias customizáveis (ocorrências/documentos)
    Route::middleware('permission:categories:manage')->group(function () {
        Route::get('configuracoes/categorias', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('configuracoes/categorias', [CategoryController::class, 'store'])->name('categories.store');
        Route::match(['put', 'patch'], 'configuracoes/categorias/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('configuracoes/categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Configurações > WhatsApp (Evolution API por tenant) — config legada de instância única.
    Route::middleware('permission:settings:whatsapp')->group(function () {
        Route::get('configuracoes/whatsapp', [WhatsappSettingController::class, 'edit'])->name('settings.whatsapp.edit');
        Route::match(['put', 'patch'], 'configuracoes/whatsapp', [WhatsappSettingController::class, 'update'])->name('settings.whatsapp.update');
        Route::post('configuracoes/whatsapp/testar', [WhatsappSettingController::class, 'test'])->name('settings.whatsapp.test');
    });

    // Inbox de WhatsApp (atendimento) — Fase 2; mídia/respostas prontas — Fase 4.
    Route::middleware('permission:inbox:use')->group(function () {
        Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
        Route::patch('inbox/assinatura', [InboxController::class, 'signature'])->name('inbox.signature');
        Route::post('inbox/nova', [InboxController::class, 'startConversation'])->name('inbox.start');
        Route::post('inbox/{conversation}/enviar', [InboxController::class, 'send'])->name('inbox.send');
        Route::post('inbox/{conversation}/midia', [InboxController::class, 'sendMedia'])->name('inbox.sendMedia');
        Route::get('inbox/midia/{object}', [InboxController::class, 'media'])->name('inbox.media');
        Route::post('inbox/{conversation}/atribuir', [InboxController::class, 'assign'])->name('inbox.assign');
        Route::post('inbox/{conversation}/status', [InboxController::class, 'toggleStatus'])->name('inbox.status');
    });

    // Conexão do WhatsApp — múltiplas conexões licenciadas (Evolution gerenciada por nós).
    Route::middleware('permission:settings:whatsapp')->group(function () {
        Route::get('configuracoes/whatsapp/conexoes', [WhatsappConnectionController::class, 'index'])->name('whatsapp.connections.index');
        Route::post('configuracoes/whatsapp/conexoes', [WhatsappConnectionController::class, 'store'])->name('whatsapp.connections.store');
        Route::get('configuracoes/whatsapp/conexoes/{connection}/qr', [WhatsappConnectionController::class, 'connect'])->name('whatsapp.connections.qr');
        Route::get('configuracoes/whatsapp/conexoes/{connection}/estado', [WhatsappConnectionController::class, 'state'])->name('whatsapp.connections.state');
        Route::match(['put', 'patch'], 'configuracoes/whatsapp/conexoes/{connection}/condominios', [WhatsappConnectionController::class, 'syncCondominiums'])->name('whatsapp.connections.condominiums');
        Route::delete('configuracoes/whatsapp/conexoes/{connection}', [WhatsappConnectionController::class, 'destroy'])->name('whatsapp.connections.destroy');
    });

    // Setores de atendimento + chatbot de triagem — Fase 3 (WhatsApp).
    Route::middleware('permission:sectors:manage')->group(function () {
        Route::get('setores', [SectorController::class, 'index'])->name('sectors.index');
        Route::post('setores', [SectorController::class, 'store'])->name('sectors.store');
        Route::match(['put', 'patch'], 'setores/{sector}', [SectorController::class, 'update'])->name('sectors.update');
        Route::match(['put', 'patch'], 'setores/{sector}/membros', [SectorController::class, 'syncMembers'])->name('sectors.members');
        Route::delete('setores/{sector}', [SectorController::class, 'destroy'])->name('sectors.destroy');

        Route::get('configuracoes/chatbot', [WhatsappBotController::class, 'index'])->name('chatbot.index');
        Route::match(['put', 'patch'], 'configuracoes/chatbot/conexao/{connection}', [WhatsappBotController::class, 'updateConnection'])->name('chatbot.connection');
        Route::match(['put', 'patch'], 'configuracoes/chatbot/condominio/{condominium}', [WhatsappBotController::class, 'updateCondominium'])->name('chatbot.condominium');

        // Relatórios de atendimento (WhatsApp) — Fase 5.
        Route::get('inbox/relatorios', [WhatsappReportController::class, 'index'])->name('inbox.reports');

        // Respostas prontas (canned) — Fase 4.
        Route::get('respostas-rapidas', [QuickReplyController::class, 'index'])->name('quick-replies.index');
        Route::post('respostas-rapidas', [QuickReplyController::class, 'store'])->name('quick-replies.store');
        Route::match(['put', 'patch'], 'respostas-rapidas/{quickReply}', [QuickReplyController::class, 'update'])->name('quick-replies.update');
        Route::delete('respostas-rapidas/{quickReply}', [QuickReplyController::class, 'destroy'])->name('quick-replies.destroy');
    });

    // Disparo em massa por WhatsApp — Fase 6 (iniciativa). Rotas estáticas antes de {campaign}.
    Route::middleware('permission:campaigns:manage')->group(function () {
        Route::get('disparos', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('disparos/criar', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::get('disparos/condominio/{condominium}/alvos', [CampaignController::class, 'targets'])->name('campaigns.targets');
        Route::post('disparos/previa', [CampaignController::class, 'preview'])->name('campaigns.preview');
        Route::post('disparos', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('disparos/descadastros', [CampaignController::class, 'optOuts'])->name('campaigns.optouts');
        Route::post('disparos/descadastros', [CampaignController::class, 'storeOptOut'])->name('campaigns.optouts.store');
        Route::delete('disparos/descadastros/{optOut}', [CampaignController::class, 'destroyOptOut'])->name('campaigns.optouts.destroy');
        Route::get('disparos/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::post('disparos/{campaign}/enviar', [CampaignController::class, 'start'])->name('campaigns.start');
        Route::post('disparos/{campaign}/cancelar', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::delete('disparos/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    });
    }); // fim do painel administrativo (middleware 'panel')
});

require __DIR__.'/auth.php';
