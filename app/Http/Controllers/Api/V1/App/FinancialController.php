<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Models\Charge;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Financeiro (leitura) para o app do síndico: KPIs, cobranças e contas a pagar.
 * Espelha as consultas de Panel\ChargeController / Panel\ExpenseController.
 */
class FinancialController extends AppController
{
    #[OA\Get(
        path: '/v1/app/charges',
        operationId: 'appChargesIndex',
        summary: 'Listar cobranças com KPIs (filtros: condominium_id, status, reference_month, search)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada + KPIs')],
    )]
    public function charges(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        $charges = Charge::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'unit:id,number', 'person:id,name'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->reference_month, fn ($q, $m) => $q->where('reference_month', $m))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'ilike', "%{$s}%"))
            ->orderByDesc('due_date')
            ->paginate(min((int) $request->query('per_page', 20), 50));

        $base = Charge::where('tenant_id', $tenant->id)
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id));

        $kpis = [
            'open' => (float) (clone $base)->whereIn('status', ['pending', 'overdue'])->sum('amount'),
            'overdue' => (float) (clone $base)->overdue()->sum('amount'),
            'received_month' => (float) (clone $base)->where('status', 'paid')
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('paid_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $charges->getCollection()->map(fn (Charge $c) => $this->chargePayload($c)),
            'meta' => [
                'current_page' => $charges->currentPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
                'last_page' => $charges->lastPage(),
            ],
            'kpis' => $kpis,
            'options' => [
                'statuses' => Charge::STATUSES,
                'condominiums' => $this->condominiumOptions(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/v1/app/charges/{id}',
        operationId: 'appChargesShow',
        summary: 'Detalhe da cobrança',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Cobrança')],
    )]
    public function chargeShow(Charge $charge): JsonResponse
    {
        $this->authorizeTenant($charge);
        $charge->load(['condominium:id,name', 'unit:id,number', 'person:id,name']);

        return $this->ok($this->chargePayload($charge, full: true));
    }

    #[OA\Get(
        path: '/v1/app/expenses',
        operationId: 'appExpensesIndex',
        summary: 'Listar contas a pagar (filtros: condominium_id, status, from, to)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada')],
    )]
    public function expenses(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        $expenses = Expense::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'supplierRecord:id,name'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->status === 'open', fn ($q) => $q->open())
            ->when($request->status && $request->status !== 'open', fn ($q) => $q->where('status', $request->status))
            ->when($request->from, fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->orderBy('due_date')
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'success' => true,
            'data' => $expenses->getCollection()->map(fn (Expense $e) => $this->expensePayload($e)),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'last_page' => $expenses->lastPage(),
            ],
            'options' => [
                'statuses' => Expense::STATUSES,
                'condominiums' => $this->condominiumOptions(),
            ],
        ]);
    }

    private function chargePayload(Charge $c, bool $full = false): array
    {
        $base = [
            'id' => $c->id,
            'description' => $c->description,
            'amount' => (float) $c->amount,
            'current_amount' => $c->isOverdue() ? $c->currentAmount() : (float) $c->amount,
            'due_date' => $c->due_date?->toDateString(),
            'status' => $c->isOverdue() ? 'overdue' : $c->status,
            'status_label' => $c->isOverdue() ? 'Vencido' : (Charge::STATUSES[$c->status] ?? $c->status),
            'reference_month' => $c->reference_month,
            'condominium' => $c->condominium ? ['id' => $c->condominium->id, 'name' => $c->condominium->name] : null,
            'unit' => $c->unit ? ['id' => $c->unit->id, 'number' => $c->unit->number] : null,
            'person' => $c->person ? ['id' => $c->person->id, 'name' => $c->person->name] : null,
        ];

        if ($full) {
            $base['type'] = $c->type;
            $base['paid_at'] = $c->paid_at?->toIso8601String();
            $base['paid_amount'] = $c->paid_amount !== null ? (float) $c->paid_amount : null;
            $base['fine_rate'] = $c->fine_rate !== null ? (float) $c->fine_rate : null;
            $base['interest_rate'] = $c->interest_rate !== null ? (float) $c->interest_rate : null;
            $base['has_gateway_charge'] = $c->hasGatewayCharge();
        }

        return $base;
    }

    private function expensePayload(Expense $e): array
    {
        return [
            'id' => $e->id,
            'description' => $e->description,
            'category' => $e->category,
            'amount' => (float) $e->amount,
            'due_date' => $e->due_date?->toDateString(),
            'status' => $e->display_status,
            'status_label' => $e->display_status_label,
            'supplier' => $e->supplierRecord?->name ?? $e->supplier,
            'condominium' => $e->condominium ? ['id' => $e->condominium->id, 'name' => $e->condominium->name] : null,
            'paid_at' => $e->paid_at?->toIso8601String(),
        ];
    }
}
