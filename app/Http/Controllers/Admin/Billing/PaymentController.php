<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = BillingPayment::query()
            ->with('tenant:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->nfse, fn ($q, $s) => $q->where('nfse_status', $s))
            ->when($request->search, fn ($q, $s) => $q->whereHas('tenant', fn ($t) => $t->where('name', 'ilike', "%{$s}%")))
            ->latest('due_date')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Billing/Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['status', 'nfse', 'search']),
        ]);
    }
}
