<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Condominium;
use App\Models\Expense;
use App\Models\MaintenancePlan;
use App\Models\Quotation;
use App\Models\QuotationProposal;
use App\Models\Supplier;
use App\Services\StorageService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $quotations = Quotation::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'approvedProposal:id,quotation_id,supplier_name,amount'])
            ->withCount('proposals')
            ->when($request->status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->condominium_id, fn (Builder $q, string $id) => $q->where('condominium_id', $id))
            ->when($request->category, fn (Builder $q, string $category) => $q->where('category', $category))
            ->when($request->search, function (Builder $q, string $search) {
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%"));
            })
            ->orderByRaw("CASE WHEN status = 'collecting' THEN 1 WHEN status = 'draft' THEN 2 WHEN status = 'approved' THEN 3 ELSE 4 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'summary' => $this->summary($tenant->id),
            'categories' => $this->categoryOptions($tenant->id),
            'statuses' => Quotation::STATUSES,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'filters' => $request->only(['status', 'condominium_id', 'category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Quotations/Create', [
            'categories' => $this->categoryOptions($tenant->id),
            'statuses' => $this->editableStatuses(),
            'condominiums' => $this->condominiumOptions($tenant->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->quotationData($request, $tenant->id);

        $quotation = Quotation::create([
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()?->id,
            ...$data,
        ]);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Orçamento criado.');
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        $quotation = $this->authorizeQuotation($quotation);
        $quotation->load([
            'condominium:id,name',
            'creator:id,name',
            'approver:id,name',
            'approvedProposal:id,quotation_id,supplier_name,amount',
            'proposals.supplier:id,name',
            'proposals.attachments:id,entity_id,original_filename,file_size_bytes,created_at',
            'proposals.expense:id,quotation_proposal_id,status,due_date,amount,description',
            'proposals.maintenancePlan:id,quotation_proposal_id,title',
        ]);

        return Inertia::render('Quotations/Show', [
            'quotation' => $quotation,
            'categories' => $this->categoryOptions($quotation->tenant_id),
            'proposalStatuses' => QuotationProposal::STATUSES,
            'suppliers' => $this->supplierOptions($quotation->tenant_id),
            'frequencies' => MaintenancePlan::FREQUENCIES,
            'canGenerateExpense' => $this->canGenerateExpense($request),
            'canCreateMaintenance' => $this->canCreateMaintenance($request),
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        $quotation = $this->authorizeQuotation($quotation);

        return Inertia::render('Quotations/Edit', [
            'quotation' => $quotation,
            'categories' => $this->categoryOptions($quotation->tenant_id),
            'statuses' => $this->editableStatuses(),
            'condominiums' => $this->condominiumOptions($quotation->tenant_id),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $quotation = $this->authorizeQuotation($quotation);
        abort_if($quotation->status === 'approved', 422, 'Orçamento aprovado não pode ser editado.');

        $quotation->update($this->quotationData($request, $quotation->tenant_id));

        return redirect()->route('quotations.show', $quotation)->with('success', 'Orçamento atualizado.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $quotation = $this->authorizeQuotation($quotation);
        abort_if($quotation->status === 'approved', 422, 'Orçamento aprovado não pode ser removido.');

        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Orçamento removido.');
    }

    public function storeProposal(Request $request, Quotation $quotation): RedirectResponse
    {
        $quotation = $this->authorizeQuotation($quotation);
        $this->assertCanReceiveProposal($quotation);

        $data = $this->proposalData($request, $quotation);
        $supplier = Supplier::where('tenant_id', $quotation->tenant_id)->findOrFail($data['supplier_id']);

        if ($quotation->proposals()->where('supplier_id', $supplier->id)->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Este fornecedor já possui proposta neste orçamento.',
            ]);
        }

        $proposal = $quotation->proposals()->create([
            'tenant_id' => $quotation->tenant_id,
            'created_by' => $request->user()?->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'amount' => $data['amount'],
            'execution_days' => $data['execution_days'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'status' => 'received',
            'submitted_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->storage->upload(
                    file: $file,
                    tenant: app('tenant'),
                    entityType: 'quotation_proposal',
                    entityId: $proposal->id,
                    visibility: 'tenant',
                    condominiumId: $quotation->condominium_id,
                );
            }
        }

        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'collecting']);
        }

        return back()->with('success', 'Proposta registrada.');
    }

    public function destroyProposal(QuotationProposal $proposal): RedirectResponse
    {
        $proposal = $this->authorizeProposal($proposal);
        abort_if($proposal->status === 'approved' || $proposal->quotation->status === 'approved', 422, 'Proposta aprovada não pode ser removida.');

        foreach ($proposal->attachments as $attachment) {
            $this->storage->delete($attachment);
        }

        $proposal->delete();

        return back()->with('success', 'Proposta removida.');
    }

    public function approveProposal(Request $request, QuotationProposal $proposal): RedirectResponse
    {
        $proposal = $this->authorizeProposal($proposal);
        $quotation = $proposal->quotation;
        abort_if($quotation->status === 'approved', 422, 'Este orçamento já foi aprovado.');
        abort_if(in_array($quotation->status, ['cancelled', 'rejected'], true), 422, 'Orçamento encerrado não aceita aprovação.');

        $data = $request->validate([
            'generate_expense' => 'boolean',
            'expense_due_date' => [Rule::requiredIf($request->boolean('generate_expense')), 'nullable', 'date'],
            'expense_document_number' => 'nullable|string|max:80',
            'expense_reminder_days' => 'nullable|integer|min:0|max:60',
            'generate_maintenance' => 'boolean',
            'maintenance_frequency' => [Rule::requiredIf($request->boolean('generate_maintenance')), 'nullable', Rule::in(array_keys(MaintenancePlan::FREQUENCIES))],
            'maintenance_next_due_date' => [Rule::requiredIf($request->boolean('generate_maintenance')), 'nullable', 'date'],
            'maintenance_alert_days' => 'nullable|integer|min:0|max:365',
        ]);

        $data['generate_expense'] = $request->boolean('generate_expense');
        $data['generate_maintenance'] = $request->boolean('generate_maintenance');

        if ($data['generate_expense']) {
            abort_unless($this->canGenerateExpense($request), 403);
        }
        if ($data['generate_maintenance']) {
            abort_unless($this->canCreateMaintenance($request), 403);
        }

        DB::transaction(function () use ($quotation, $proposal, $data, $request) {
            $quotation->proposals()
                ->where('id', '!=', $proposal->id)
                ->update(['status' => 'rejected']);

            $proposal->update(['status' => 'approved']);

            $quotation->update([
                'status' => 'approved',
                'approved_proposal_id' => $proposal->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            if ($data['generate_maintenance']) {
                $this->createMaintenanceFromProposal($quotation, $proposal, $data);
            }

            if ($data['generate_expense']) {
                $this->createExpenseFromProposal($quotation, $proposal, $data, $request);
            }
        });

        return back()->with('success', 'Proposta aprovada.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $quotation = $this->authorizeQuotation($quotation);
        abort_if($quotation->status === 'approved', 422, 'Orçamento aprovado não pode ser reprovado.');

        $data = $request->validate(['notes' => 'nullable|string|max:5000']);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->proposals()->where('status', 'received')->update(['status' => 'rejected']);
            $quotation->update([
                'status' => 'rejected',
                'notes' => $this->appendNote($quotation->notes, $data['notes'] ?? 'Reprovado sem proposta aprovada.'),
            ]);
        });

        return back()->with('success', 'Orçamento reprovado.');
    }

    private function quotationData(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'category' => ['nullable', 'string', Rule::in(array_keys($this->categoryOptions($tenantId)))],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'status' => ['required', Rule::in(array_keys($this->editableStatuses()))],
            'response_deadline' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    private function proposalData(Request $request, Quotation $quotation): array
    {
        return $request->validate([
            'supplier_id' => "required|uuid|exists:suppliers,id,tenant_id,{$quotation->tenant_id}",
            'amount' => 'required|numeric|min:0.01',
            'execution_days' => 'nullable|integer|min:0|max:3650',
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);
    }

    private function createMaintenanceFromProposal(Quotation $quotation, QuotationProposal $proposal, array $data): MaintenancePlan
    {
        $category = array_key_exists($quotation->category, Category::optionsFor($quotation->tenant_id, 'maintenance', MaintenancePlan::CATEGORIES))
            ? $quotation->category
            : 'outros';

        return MaintenancePlan::create([
            'tenant_id' => $quotation->tenant_id,
            'condominium_id' => $quotation->condominium_id,
            'supplier_id' => $proposal->supplier_id,
            'quotation_proposal_id' => $proposal->id,
            'category' => $category,
            'title' => $quotation->title,
            'description' => trim(implode("\n\n", array_filter([
                $quotation->description,
                "Gerada pela proposta aprovada do orçamento {$quotation->title}.",
                $proposal->notes ? "Observações da proposta: {$proposal->notes}" : null,
            ]))) ?: null,
            'frequency' => $data['maintenance_frequency'] ?? 'once',
            'next_due_date' => Carbon::parse($data['maintenance_next_due_date']),
            'alert_days' => $data['maintenance_alert_days'] ?? 15,
            'is_active' => true,
        ]);
    }

    private function createExpenseFromProposal(Quotation $quotation, QuotationProposal $proposal, array $data, Request $request): Expense
    {
        return Expense::create([
            'tenant_id' => $quotation->tenant_id,
            'condominium_id' => $quotation->condominium_id,
            'category' => ($data['generate_maintenance'] ?? false) ? 'maintenance' : 'other',
            'description' => "Orçamento aprovado: {$quotation->title}",
            'amount' => $proposal->amount,
            'status' => 'pending',
            'expense_date' => today(),
            'due_date' => Carbon::parse($data['expense_due_date']),
            'supplier' => $proposal->supplier_name,
            'supplier_id' => $proposal->supplier_id,
            'document_number' => $data['expense_document_number'] ?? null,
            'reminder_days' => $data['expense_reminder_days'] ?? 3,
            'quotation_proposal_id' => $proposal->id,
            'notes' => trim(implode("\n\n", array_filter([
                "Gerada pela proposta aprovada do orçamento {$quotation->title}.",
                $proposal->notes,
            ]))) ?: null,
            'created_by' => $request->user()?->id,
        ]);
    }

    private function assertCanReceiveProposal(Quotation $quotation): void
    {
        abort_if(in_array($quotation->status, ['approved', 'rejected', 'cancelled'], true), 422, 'Orçamento encerrado não aceita novas propostas.');
    }

    private function editableStatuses(): array
    {
        return [
            'draft' => Quotation::STATUSES['draft'],
            'collecting' => Quotation::STATUSES['collecting'],
            'cancelled' => Quotation::STATUSES['cancelled'],
        ];
    }

    private function summary(string $tenantId): array
    {
        return [
            'collecting' => Quotation::where('tenant_id', $tenantId)->where('status', 'collecting')->count(),
            'draft' => Quotation::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'approved' => Quotation::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
            'proposals_total' => QuotationProposal::where('tenant_id', $tenantId)->count(),
        ];
    }

    private function categoryOptions(string $tenantId): array
    {
        return Category::optionsFor($tenantId, 'quotation', Quotation::CATEGORIES);
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

    private function canGenerateExpense(Request $request): bool
    {
        return $request->user()?->hasPermission('expenses:create')
            && $this->planAllows('financial', $request);
    }

    private function canCreateMaintenance(Request $request): bool
    {
        return $request->user()?->hasPermission('maintenance:create')
            && $this->planAllows('maintenance', $request);
    }

    private function planAllows(string $module, Request $request): bool
    {
        if ($request->user()?->isSuperAdmin()) {
            return true;
        }

        return (bool) app('tenant')->activePlan()?->hasModule($module);
    }

    private function appendNote(?string $current, ?string $note): ?string
    {
        return trim(implode("\n\n", array_filter([$current, $note]))) ?: null;
    }

    private function authorizeQuotation(Quotation $quotation): Quotation
    {
        abort_unless($quotation->tenant_id === app('tenant')->id, 403);

        return $quotation;
    }

    private function authorizeProposal(QuotationProposal $proposal): QuotationProposal
    {
        abort_unless($proposal->tenant_id === app('tenant')->id, 403);
        $proposal->loadMissing('quotation', 'attachments');

        return $proposal;
    }
}
