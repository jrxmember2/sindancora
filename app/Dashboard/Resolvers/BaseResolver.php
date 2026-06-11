<?php

namespace App\Dashboard\Resolvers;

use App\Dashboard\Contracts\WidgetDataResolver;
use App\Dashboard\DashboardContext;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\Document;
use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\Occurrence;
use App\Models\PersonUnitLink;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Base dos resolvers de widget: helpers de consulta com escopo de condomínio já
 * aplicado, no mesmo padrão de ConsolidatedReportBuilder (evita N+1 com agregações).
 */
abstract class BaseResolver implements WidgetDataResolver
{
    protected function chargeBase(DashboardContext $ctx): Builder
    {
        return Charge::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function expenseBase(DashboardContext $ctx): Builder
    {
        return Expense::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function occurrenceBase(DashboardContext $ctx): Builder
    {
        return Occurrence::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function reservationBase(DashboardContext $ctx): Builder
    {
        return Reservation::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function maintenanceBase(DashboardContext $ctx): Builder
    {
        return MaintenancePlan::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function workBase(DashboardContext $ctx): Builder
    {
        return Work::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function documentBase(DashboardContext $ctx): Builder
    {
        return Document::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    protected function unitBase(DashboardContext $ctx): Builder
    {
        return Unit::where('tenant_id', $ctx->tenant->id)->whereIn('condominium_id', $ctx->scopeIds());
    }

    /** IDs das unidades dentro do escopo (para escopar pessoas via person_unit_links). */
    protected function unitIds(DashboardContext $ctx): array
    {
        return $this->unitBase($ctx)->pluck('id')->all();
    }

    /** Mapa id => nome dos condomínios no escopo. */
    protected function condoNames(DashboardContext $ctx): Collection
    {
        return Condominium::where('tenant_id', $ctx->tenant->id)
            ->whereIn('id', $ctx->scopeIds())
            ->pluck('name', 'id');
    }

    /** Moradores distintos vinculados às unidades do escopo (vínculos ativos). */
    protected function residentLinkBase(DashboardContext $ctx): Builder
    {
        return PersonUnitLink::where('tenant_id', $ctx->tenant->id)
            ->whereIn('unit_id', $this->unitIds($ctx))
            ->where(function (Builder $q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString());
            });
    }

    /** Empty state padrão (sem escopo de condomínio). */
    protected function empty(array $extra = []): array
    {
        return array_merge(['empty' => true], $extra);
    }
}
