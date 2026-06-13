<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use App\Models\PendingSignup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingDashboardController extends Controller
{
    public function index(): Response
    {
        $statusCounts = BillingSubscription::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // MRR: valor mensal equivalente das assinaturas que ainda geram receita.
        $mrr = BillingSubscription::query()
            ->whereIn('status', [
                BillingSubscription::STATUS_ACTIVE,
                BillingSubscription::STATUS_OVERDUE,
                BillingSubscription::STATUS_GRACE_MANUAL,
                BillingSubscription::STATUS_GRACE_TRUST,
            ])
            ->get(['value', 'billing_cycle'])
            ->sum(fn ($s) => $s->billing_cycle === 'yearly' ? (float) $s->value / 12 : (float) $s->value);

        $now = Carbon::now();
        $revenueMonth = BillingPayment::whereIn('status', BillingPayment::PAID_STATUSES)
            ->whereBetween('payment_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->sum('value');

        $canceledMonth = BillingSubscription::where('status', BillingSubscription::STATUS_CANCELED)
            ->whereBetween('canceled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $activeBase = (int) ($statusCounts[BillingSubscription::STATUS_ACTIVE] ?? 0)
            + (int) ($statusCounts[BillingSubscription::STATUS_OVERDUE] ?? 0);
        $churn = $activeBase > 0 ? round($canceledMonth / ($activeBase + $canceledMonth) * 100, 1) : 0;

        // Receita dos últimos 6 meses (gráfico).
        $revenueSeries = collect(range(5, 0))->map(function ($back) use ($now) {
            $month = $now->copy()->subMonths($back);

            return [
                'label' => $month->format('m/Y'),
                'value' => (float) BillingPayment::whereIn('status', BillingPayment::PAID_STATUSES)
                    ->whereBetween('payment_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->sum('value'),
            ];
        })->values();

        return Inertia::render('Admin/Billing/Dashboard', [
            'metrics' => [
                'mrr' => round($mrr, 2),
                'active' => (int) ($statusCounts[BillingSubscription::STATUS_ACTIVE] ?? 0),
                'overdue' => (int) ($statusCounts[BillingSubscription::STATUS_OVERDUE] ?? 0),
                'suspended' => (int) ($statusCounts[BillingSubscription::STATUS_SUSPENDED] ?? 0),
                'grace' => (int) ($statusCounts[BillingSubscription::STATUS_GRACE_MANUAL] ?? 0)
                    + (int) ($statusCounts[BillingSubscription::STATUS_GRACE_TRUST] ?? 0),
                'canceled' => (int) ($statusCounts[BillingSubscription::STATUS_CANCELED] ?? 0),
                'revenue_month' => (float) $revenueMonth,
                'churn' => $churn,
                'pending_signups' => PendingSignup::where('status', 'pending')->count(),
                'failed_signups' => PendingSignup::where('status', 'failed')->count(),
                'nfse_errors' => BillingPayment::where('nfse_status', 'error')->count(),
            ],
            'revenueSeries' => $revenueSeries,
        ]);
    }
}
