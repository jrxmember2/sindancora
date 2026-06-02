<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Expense;
use App\Services\StorageService;
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

        $expenses = Expense::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->from, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        $total = Expense::where('tenant_id', $tenant->id)
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->from, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->sum('amount');

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'total' => $total,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Expense::CATEGORIES,
            'filters' => $request->only(['condominium_id', 'category', 'from', 'to']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Expenses/Create', [
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id, withFile: true);

        $expense = Expense::create([
            'tenant_id' => $tenant->id,
            'condominium_id' => $data['condominium_id'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'supplier' => $data['supplier'] ?? null,
            'notes' => $data['notes'] ?? null,
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

        return redirect()->route('expenses.index')->with('success', 'Despesa lançada.');
    }

    public function edit(Expense $expense): Response
    {
        $expense = $this->authorizeTenant($expense);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load('receipt:id'),
            'condominiums' => $this->condominiumOptions($expense->tenant_id),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense = $this->authorizeTenant($expense);
        $data = $this->validated($request, $expense->tenant_id, withFile: false);

        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Despesa atualizada.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense = $this->authorizeTenant($expense);

        if ($expense->receipt) {
            $this->storage->delete($expense->receipt);
        }
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Despesa removida.');
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

    private function validated(Request $request, string $tenantId, bool $withFile): array
    {
        $rules = [
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'category' => 'required|in:'.implode(',', array_keys(Expense::CATEGORIES)),
            'description' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'supplier' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($withFile) {
            $rules['receipt'] = 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp';
        }

        return $request->validate($rules);
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
}
