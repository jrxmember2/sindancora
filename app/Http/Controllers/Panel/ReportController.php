<?php

namespace App\Http\Controllers\Panel;

use App\Exports\FinancialReportExport;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\Expense;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$from, $to, $condominiumId] = $this->params($request);

        return Inertia::render('Reports/Financial', [
            'report' => $this->build($from, $to, $condominiumId),
            'condominiums' => $this->condominiumOptions(),
            'filters' => [
                'condominium_id' => $condominiumId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'canExport' => $request->user()->hasPermission('reports:export'),
        ]);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$from, $to, $condominiumId] = $this->params($request);
        $data = $this->build($from, $to, $condominiumId);

        $pdf = Pdf::loadView('reports.financial', [
            'report' => $data,
            'tenant' => app('tenant'),
            'from' => $from,
            'to' => $to,
        ]);

        return $pdf->download('prestacao-de-contas-'.$from->format('Y-m-d').'-a-'.$to->format('Y-m-d').'.pdf');
    }

    public function exportXlsx(Request $request): BinaryFileResponse
    {
        [$from, $to, $condominiumId] = $this->params($request);
        $data = $this->build($from, $to, $condominiumId);

        return Excel::download(
            new FinancialReportExport($data, $from, $to),
            'relatorio-financeiro-'.$from->format('Y-m-d').'-a-'.$to->format('Y-m-d').'.xlsx',
        );
    }

    /** @return array{0:Carbon,1:Carbon,2:?string} */
    private function params(Request $request): array
    {
        $from = $this->parseDate($request->from) ?? Carbon::now()->startOfYear();
        $to = $this->parseDate($request->to) ?? Carbon::now()->endOfDay();
        $condominiumId = $request->condominium_id ?: null;

        return [$from->startOfDay(), $to->endOfDay(), $condominiumId];
    }

    private function parseDate(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Agrega os números do período: cobrado, recebido, em aberto, contas pagas, saldo,
     * inadimplência e a quebra mensal.
     */
    private function build(Carbon $from, Carbon $to, ?string $condominiumId): array
    {
        $tenant = app('tenant');

        $chargeBase = fn () => Charge::where('tenant_id', $tenant->id)
            ->when($condominiumId, fn ($q, $id) => $q->where('condominium_id', $id));
        $expenseBase = fn () => Expense::where('tenant_id', $tenant->id)
            ->when($condominiumId, fn ($q, $id) => $q->where('condominium_id', $id));

        $charged = (clone $chargeBase())->where('status', '!=', 'cancelled')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        $received = (clone $chargeBase())->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])->sum('paid_amount');

        $open = (clone $chargeBase())->whereIn('status', ['pending', 'overdue'])->sum('amount');

        $expenses = (clone $expenseBase())
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('paid_amount');

        // Inadimplentes: cobranças em aberto e vencidas (por unidade).
        $delinquentCharges = (clone $chargeBase())->overdue()
            ->with(['unit:id,number', 'person:id,name'])
            ->get();

        $delinquents = $delinquentCharges
            ->groupBy('unit_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'unit' => $first->unit?->number ?? '—',
                    'person' => $first->person?->name ?? '—',
                    'count' => $group->count(),
                    'total' => round($group->sum(fn ($c) => $c->currentAmount()), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Quebra mensal dentro do período.
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $mStart = $cursor->copy()->startOfMonth();
            $mEnd = $cursor->copy()->endOfMonth();

            $months[] = [
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->locale('pt_BR')->isoFormat('MMM/YYYY'),
                'charged' => (float) (clone $chargeBase())->where('status', '!=', 'cancelled')
                    ->whereBetween('due_date', [$mStart->toDateString(), $mEnd->toDateString()])->sum('amount'),
                'received' => (float) (clone $chargeBase())->where('status', 'paid')
                    ->whereBetween('paid_at', [$mStart, $mEnd])->sum('paid_amount'),
                'expenses' => (float) (clone $expenseBase())
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$mStart, $mEnd])
                    ->sum('paid_amount'),
            ];

            $cursor->addMonth();
        }

        return [
            'summary' => [
                'charged' => (float) $charged,
                'received' => (float) $received,
                'open' => (float) $open,
                'expenses' => (float) $expenses,
                'balance' => round((float) $received - (float) $expenses, 2),
                'overdue_total' => (float) $delinquents->sum('total'),
                'delinquent_units' => $delinquents->count(),
            ],
            'delinquents' => $delinquents,
            'months' => $months,
        ];
    }

    private function condominiumOptions(): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', app('tenant')->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
