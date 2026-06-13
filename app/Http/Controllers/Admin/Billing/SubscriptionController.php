<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSubscription;
use App\Models\BillingTimelineEntry;
use App\Services\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): Response
    {
        $subscriptions = BillingSubscription::query()
            ->with(['tenant:id,name,status,email', 'plan:id,display_name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('tenant', fn ($t) => $t->where('name', 'ilike', "%{$s}%")))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Billing/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(BillingSubscription $subscription): Response
    {
        $subscription->load(['tenant:id,name,status,email,document', 'plan:id,display_name']);

        return Inertia::render('Admin/Billing/Subscriptions/Show', [
            'subscription' => $subscription,
            'payments' => $subscription->payments()->latest('due_date')->limit(24)->get(),
            'timeline' => BillingTimelineEntry::where('tenant_id', $subscription->tenant_id)
                ->latest()->limit(50)->get(),
        ]);
    }

    public function grantGrace(Request $request, BillingSubscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'mode' => 'required|in:date,next_due',
            'until' => 'required_if:mode,date|nullable|date|after:today',
        ]);

        $until = $data['mode'] === 'next_due'
            ? ($subscription->next_due_date?->copy() ?? Carbon::today()->addDays(7))
            : Carbon::parse($data['until']);

        $this->billing->grantManualGrace($subscription, $data['reason'], $until, Auth::user());

        return back()->with('success', 'Tenant desbloqueado manualmente (em carência) até '.$until->format('d/m/Y').'.');
    }

    public function revokeGrace(BillingSubscription $subscription): RedirectResponse
    {
        $this->billing->revokeGrace($subscription, Auth::user());

        return back()->with('success', 'Carência revogada. Tenant voltou a suspenso.');
    }

    public function suspend(BillingSubscription $subscription): RedirectResponse
    {
        $this->billing->suspend($subscription, 'Bloqueio manual pelo super admin '.Auth::user()->name.'.');

        return back()->with('success', 'Tenant bloqueado.');
    }

    public function cancel(BillingSubscription $subscription): RedirectResponse
    {
        $this->billing->cancel($subscription);

        return back()->with('success', 'Assinatura cancelada.');
    }
}
