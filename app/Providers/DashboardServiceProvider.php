<?php

namespace App\Providers;

use App\Dashboard\Resolvers\Documents\RecentDocumentsResolver;
use App\Dashboard\Resolvers\Financial\ChargeStatusDonutResolver;
use App\Dashboard\Resolvers\Financial\DelinquencyRankingResolver;
use App\Dashboard\Resolvers\Financial\DelinquencyTrendResolver;
use App\Dashboard\Resolvers\Financial\DelinquentUnitsResolver;
use App\Dashboard\Resolvers\Financial\MonthBalanceResolver;
use App\Dashboard\Resolvers\Financial\OpenAmountResolver;
use App\Dashboard\Resolvers\Financial\PayablesDueResolver;
use App\Dashboard\Resolvers\Financial\ReceivedThisMonthResolver;
use App\Dashboard\Resolvers\Financial\RevenueVsExpenseResolver;
use App\Dashboard\Resolvers\General\NewRegistrationsResolver;
use App\Dashboard\Resolvers\General\StorageGaugeResolver;
use App\Dashboard\Resolvers\General\TotalCondominiumsResolver;
use App\Dashboard\Resolvers\General\TotalResidentsResolver;
use App\Dashboard\Resolvers\General\TotalUnitsResolver;
use App\Dashboard\Resolvers\Occurrences\OccurrenceStatusDonutResolver;
use App\Dashboard\Resolvers\Occurrences\OpenOccurrencesResolver;
use App\Dashboard\Resolvers\Occurrences\SlaAtRiskResolver;
use App\Dashboard\Resolvers\Operations\ActiveWorksResolver;
use App\Dashboard\Resolvers\Operations\UpcomingMaintenanceResolver;
use App\Dashboard\Resolvers\Reservations\PendingReservationsResolver;
use App\Dashboard\Resolvers\Reservations\UpcomingReservationsResolver;
use App\Dashboard\Resolvers\Shortcuts\QuickActionsResolver;
use App\Dashboard\WidgetDefinition;
use App\Dashboard\WidgetRegistry;
use App\Dashboard\WidgetSize as Size;
use App\Dashboard\WidgetType as Type;
use Illuminate\Support\ServiceProvider;

/**
 * Registra o WidgetRegistry e cadastra todos os widgets do dashboard modular.
 *
 * Para adicionar um widget novo: crie um resolver (implements WidgetDataResolver)
 * e registre uma WidgetDefinition aqui. Ver docs/tecnico/dashboard-modular.md.
 */
class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetRegistry::class, function () {
            $registry = new WidgetRegistry();
            $this->registerWidgets($registry);

            return $registry;
        });
    }

    private function registerWidgets(WidgetRegistry $registry): void
    {
        foreach ($this->definitions() as $definition) {
            $registry->register($definition);
        }
    }

    /**
     * @return array<int, WidgetDefinition>
     */
    private function definitions(): array
    {
        return [
            // ── Atalhos ─────────────────────────────────────────────────────
            new WidgetDefinition(
                key: 'shortcuts.quick_actions',
                module: null,
                name: 'Ações rápidas',
                description: 'Atalhos para as criações mais comuns conforme suas permissões.',
                type: Type::QUICK_ACTIONS,
                size: Size::FULL,
                resolver: QuickActionsResolver::class,
                order: 5,
            ),

            // ── Geral ───────────────────────────────────────────────────────
            new WidgetDefinition(
                key: 'general.condominiums',
                module: null,
                name: 'Condomínios',
                description: 'Total de condomínios acessíveis.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: TotalCondominiumsResolver::class,
                order: 10,
            ),
            new WidgetDefinition(
                key: 'general.units',
                module: null,
                name: 'Unidades',
                description: 'Total de unidades cadastradas.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: TotalUnitsResolver::class,
                order: 11,
            ),
            new WidgetDefinition(
                key: 'general.residents',
                module: null,
                name: 'Moradores',
                description: 'Moradores vinculados às unidades.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: TotalResidentsResolver::class,
                order: 12,
            ),
            new WidgetDefinition(
                key: 'general.new_units',
                module: null,
                name: 'Novos cadastros',
                description: 'Novas unidades cadastradas no mês, com variação.',
                type: Type::KPI_TREND,
                size: Size::SMALL,
                resolver: NewRegistrationsResolver::class,
                order: 13,
            ),
            new WidgetDefinition(
                key: 'general.storage',
                module: null,
                name: 'Armazenamento',
                description: 'Uso do armazenamento contratado.',
                type: Type::GAUGE,
                size: Size::SMALL,
                resolver: StorageGaugeResolver::class,
                permission: 'settings:update',
                order: 14,
            ),

            // ── Cobrança / Financeiro ───────────────────────────────────────
            new WidgetDefinition(
                key: 'financial.open_amount',
                module: 'financial',
                name: 'Valor em aberto',
                description: 'Soma das cobranças pendentes e vencidas.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: OpenAmountResolver::class,
                permission: 'charges:read',
                order: 60,
            ),
            new WidgetDefinition(
                key: 'financial.received_month',
                module: 'financial',
                name: 'Recebido no mês',
                description: 'Cobranças pagas no mês, com variação.',
                type: Type::KPI_TREND,
                size: Size::SMALL,
                resolver: ReceivedThisMonthResolver::class,
                permission: 'charges:read',
                order: 61,
            ),
            new WidgetDefinition(
                key: 'financial.delinquent_units',
                module: 'financial',
                name: 'Inadimplência',
                description: 'Unidades inadimplentes e total em atraso.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: DelinquentUnitsResolver::class,
                permission: 'charges:read',
                order: 62,
            ),
            new WidgetDefinition(
                key: 'financial.month_balance',
                module: 'financial',
                name: 'Saldo do mês',
                description: 'Recebido menos despesas pagas no mês.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: MonthBalanceResolver::class,
                permission: 'charges:read',
                order: 63,
            ),
            new WidgetDefinition(
                key: 'financial.charge_status',
                module: 'financial',
                name: 'Cobranças por status',
                description: 'Distribuição das cobranças por situação.',
                type: Type::DONUT,
                size: Size::MEDIUM,
                resolver: ChargeStatusDonutResolver::class,
                permission: 'charges:read',
                order: 64,
            ),
            new WidgetDefinition(
                key: 'financial.payables_due',
                module: 'financial',
                name: 'Contas a vencer',
                description: 'Contas a pagar vencendo nos próximos dias.',
                type: Type::ALERT,
                size: Size::MEDIUM,
                resolver: PayablesDueResolver::class,
                permission: 'expenses:read',
                order: 65,
            ),
            new WidgetDefinition(
                key: 'financial.delinquency_trend',
                module: 'financial',
                name: 'Evolução da inadimplência',
                description: 'Valor em aberto por mês de vencimento (12 meses).',
                type: Type::LINE,
                size: Size::WIDE,
                resolver: DelinquencyTrendResolver::class,
                permission: 'charges:read',
                order: 66,
                lazy: true,
            ),
            new WidgetDefinition(
                key: 'financial.revenue_vs_expense',
                module: 'financial',
                name: 'Receitas x Despesas',
                description: 'Comparativo mensal de recebimentos e pagamentos (12 meses).',
                type: Type::BAR,
                size: Size::WIDE,
                resolver: RevenueVsExpenseResolver::class,
                permission: 'charges:read',
                order: 67,
                lazy: true,
            ),
            new WidgetDefinition(
                key: 'financial.delinquency_ranking',
                module: 'financial',
                name: 'Ranking de inadimplência',
                description: 'Condomínios com maior valor em atraso.',
                type: Type::RANKING,
                size: Size::MEDIUM,
                resolver: DelinquencyRankingResolver::class,
                permission: 'charges:read',
                order: 68,
                lazy: true,
            ),

            // ── Ocorrências ─────────────────────────────────────────────────
            new WidgetDefinition(
                key: 'occurrences.open',
                module: 'occurrences',
                name: 'Ocorrências abertas',
                description: 'Chamados em aberto e urgentes.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: OpenOccurrencesResolver::class,
                permission: 'occurrences:read',
                order: 70,
            ),
            new WidgetDefinition(
                key: 'occurrences.status',
                module: 'occurrences',
                name: 'Ocorrências por status',
                description: 'Distribuição dos chamados por situação.',
                type: Type::DONUT,
                size: Size::MEDIUM,
                resolver: OccurrenceStatusDonutResolver::class,
                permission: 'occurrences:read',
                order: 71,
            ),
            new WidgetDefinition(
                key: 'occurrences.sla_risk',
                module: 'occurrences',
                name: 'SLA em risco',
                description: 'Chamados com prazo vencido ou próximo.',
                type: Type::ALERT,
                size: Size::MEDIUM,
                resolver: SlaAtRiskResolver::class,
                permission: 'occurrences:read',
                order: 72,
            ),

            // ── Reservas ────────────────────────────────────────────────────
            new WidgetDefinition(
                key: 'reservations.pending',
                module: 'reservations',
                name: 'Reservas pendentes',
                description: 'Reservas aguardando aprovação.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: PendingReservationsResolver::class,
                permission: 'reservations:read',
                order: 75,
            ),
            new WidgetDefinition(
                key: 'reservations.upcoming',
                module: 'reservations',
                name: 'Próximas reservas',
                description: 'Agenda das próximas reservas.',
                type: Type::TIMELINE,
                size: Size::MEDIUM,
                resolver: UpcomingReservationsResolver::class,
                permission: 'reservations:read',
                order: 76,
            ),

            // ── Documentos ──────────────────────────────────────────────────
            new WidgetDefinition(
                key: 'documents.recent',
                module: 'documents',
                name: 'Últimos documentos',
                description: 'Documentos enviados recentemente.',
                type: Type::TIMELINE,
                size: Size::MEDIUM,
                resolver: RecentDocumentsResolver::class,
                permission: 'documents:read',
                order: 80,
            ),

            // ── Operação (manutenção / obras) ───────────────────────────────
            new WidgetDefinition(
                key: 'maintenance.upcoming',
                module: 'maintenance',
                name: 'Próximas manutenções',
                description: 'Planos preventivos com vencimento mais próximo.',
                type: Type::SUMMARY_TABLE,
                size: Size::WIDE,
                resolver: UpcomingMaintenanceResolver::class,
                permission: 'maintenance:read',
                order: 85,
            ),
            new WidgetDefinition(
                key: 'works.active',
                module: 'works',
                name: 'Obras em andamento',
                description: 'Obras/reformas ativas e prazos.',
                type: Type::KPI,
                size: Size::SMALL,
                resolver: ActiveWorksResolver::class,
                permission: 'works:read',
                order: 86,
            ),
        ];
    }
}
