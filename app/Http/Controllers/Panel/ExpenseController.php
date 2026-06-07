<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Expense;
use App\Models\Supplier;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $expenses = $this->filteredQuery($request, $tenant->id)
            ->with([
                'condominium:id,name',
                'supplierRecord:id,name',
                'maintenanceRecord:id,maintenance_plan_id',
                'maintenanceRecord.plan:id,title',
            ])
            ->orderByRaw("CASE WHEN status = 'paid' THEN 2 WHEN status = 'cancelled' THEN 3 ELSE 1 END")
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'total' => (float) $this->filteredQuery($request, $tenant->id)->sum('amount'),
            'summary' => $this->summary($tenant->id),
            'condominiums' => $this->condominiumOptions($tenant->id),
            'suppliers' => $this->supplierOptions($tenant->id),
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
            'filters' => $request->only(['condominium_id', 'category', 'supplier_id', 'status', 'from', 'to']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Expenses/Create', [
            'condominiums' => $this->condominiumOptions($tenant->id),
            'suppliers' => $this->supplierOptions($tenant->id),
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id, withFile: true);
        $status = $data['status'] ?? 'pending';

        $expense = Expense::create([
            'tenant_id' => $tenant->id,
            ...$this->payload($data, $tenant->id, $status),
            'created_by' => Auth::id(),
        ]);

        if ($request->hasFile('receipt')) {
            $object = $this->storage->upload(
                file: $request->file('receipt'),
                tenant: $tenant,
                entityType: 'expense_receipt',
                entityId: $expense->id,
                visibility: 'tenant',
                condominiumId: $data['condominium_id'],
            );
            $expense->update(['receipt_storage_object_id' => $object->id]);
        }

        return redirect()->route('expenses.index')->with('success', 'Conta a pagar cadastrada.');
    }

    public function edit(Expense $expense): Response
    {
        $expense = $this->authorizeTenant($expense);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load('receipt:id', 'maintenanceRecord.plan:id,title'),
            'condominiums' => $this->condominiumOptions($expense->tenant_id),
            'suppliers' => $this->supplierOptions($expense->tenant_id),
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense = $this->authorizeTenant($expense);
        $data = $this->validated($request, $expense->tenant_id, withFile: false);

        $expense->update($this->payload($data, $expense->tenant_id, $data['status'] ?? $expense->status));

        return redirect()->route('expenses.index')->with('success', 'Conta a pagar atualizada.');
    }

    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        $expense = $this->authorizeTenant($expense);

        $data = $request->validate([
            'paid_at' => 'nullable|date',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:'.implode(',', array_keys(Expense::PAYMENT_METHODS)),
        ]);

        $expense->update([
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now(),
            'paid_amount' => $data['paid_amount'] ?? $expense->amount,
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        return back()->with('success', 'Conta marcada como paga.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense = $this->authorizeTenant($expense);

        if ($expense->receipt) {
            $this->storage->delete($expense->receipt);
        }
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Conta removida.');
    }

    public function download(Expense $expense): RedirectResponse|StreamedResponse
    {
        $expense = $this->authorizeTenant($expense);
        $object = $expense->receipt;
        abort_unless($object, 404);

        $disk = Storage::disk($object->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($object->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }

    private function payload(array $data, string $tenantId, string $status): array
    {
        $paid = $status === 'paid';
        $supplierId = $data['supplier_id'] ?? null;

        return [
            'condominium_id' => $data['condominium_id'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'status' => $status,
            'expense_date' => $data['expense_date'] ?? $data['due_date'],
            'due_date' => $data['due_date'],
            'paid_at' => $paid ? ($data['paid_at'] ?? now()) : null,
            'paid_amount' => $paid ? ($data['paid_amount'] ?? $data['amount']) : null,
            'payment_method' => $paid ? ($data['payment_method'] ?? null) : null,
            'supplier' => $this->supplierName($tenantId, $supplierId, $data['supplier'] ?? null),
            'supplier_id' => $supplierId,
            'document_number' => $data['document_number'] ?? null,
            'reminder_days' => $data['reminder_days'] ?? 3,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function validated(Request $request, string $tenantId, bool $withFile): array
    {
        $rules = [
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'category' => 'required|in:'.implode(',', array_keys(Expense::CATEGORIES)),
            'description' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:'.implode(',', array_keys(Expense::STATUSES)),
            'expense_date' => 'nullable|date',
            'due_date' => 'required|date',
            'paid_at' => 'nullable|date',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:'.implode(',', array_keys(Expense::PAYMENT_METHODS)),
            'supplier' => 'nullable|string|max:150',
            'supplier_id' => "nullable|uuid|exists:suppliers,id,tenant_id,{$tenantId}",
            'document_number' => 'nullable|string|max:80',
            'reminder_days' => 'nullable|integer|min:0|max:60',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($withFile) {
            $rules['receipt'] = 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp';
        }

        return $request->validate($rules);
    }

    private function filteredQuery(Request $request, string $tenantId): Builder
    {
        return Expense::where('tenant_id', $tenantId)
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn ($q, $s) => $this->applyStatusFilter($q, $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('due_date', '<=', $d));
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        if ($status === 'overdue') {
            return $query->whereIn('status', ['pending', 'overdue'])
                ->whereDate('due_date', '<', today()->toDateString());
        }

        if ($status === 'pending') {
            return $query->where('status', 'pending')
                ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', today()->toDateString()));
        }

        return $query->where('status', $status);
    }

    private function summary(string $tenantId): array
    {
        return [
            'open_total' => (float) Expense::where('tenant_id', $tenantId)->open()->sum('amount'),
            'overdue_total' => (float) Expense::where('tenant_id', $tenantId)
                ->open()
                ->whereDate('due_date', '<', today()->toDateString())
                ->sum('amount'),
            'due_next_7_days' => (float) Expense::where('tenant_id', $tenantId)
                ->open()
                ->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->sum('amount'),
            'paid_this_month' => (float) Expense::where('tenant_id', $tenantId)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('paid_amount'),
        ];
    }

    private function supplierName(string $tenantId, ?string $supplierId, ?string $fallback): ?string
    {
        if (! $supplierId) {
            return $fallback;
        }

        return Supplier::where('tenant_id', $tenantId)->whereKey($supplierId)->value('name') ?? $fallback;
    }

    private function authorizeTenant(Expense $expense): Expense
    {
        abort_unless($expense->tenant_id === app('tenant')->id, 403);

        return $expense;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    private function supplierOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Supplier::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]);
    }
}
