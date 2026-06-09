<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Condominium;
use App\Models\Expense;
use App\Models\QuotationProposal;
use App\Models\Supplier;
use App\Models\Work;
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

class WorkController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $works = Work::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'supplier:id,name', 'quotation:id,title', 'quotationProposal:id,quotation_id,supplier_name,amount'])
            ->withCount('updates')
            ->withSum('expenses as expenses_total_amount', 'amount')
            ->withSum(['expenses as open_expenses_total_amount' => fn ($q) => $q->open()], 'amount')
            ->when($request->status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->type, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($request->condominium_id, fn (Builder $q, string $id) => $q->where('condominium_id', $id))
            ->when($request->supplier_id, fn (Builder $q, string $id) => $q->where('supplier_id', $id))
            ->when($request->search, function (Builder $q, string $search) {
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('responsible_name', 'ilike', "%{$search}%"));
            })
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'approved' THEN 2 WHEN status = 'planned' THEN 3 WHEN status = 'budgeting' THEN 4 WHEN status = 'paused' THEN 5 ELSE 6 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Works/Index', [
            'works' => $works,
            'summary' => $this->summary($tenant->id),
            'types' => $this->typeOptions($tenant->id),
            'statuses' => Work::STATUSES,
            'priorities' => Work::PRIORITIES,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'suppliers' => $this->supplierOptions($tenant->id),
            'filters' => $request->only(['status', 'type', 'condominium_id', 'supplier_id', 'search']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Works/Create', [
            'types' => $this->typeOptions($tenant->id),
            'statuses' => Work::STATUSES,
            'priorities' => Work::PRIORITIES,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'suppliers' => $this->supplierOptions($tenant->id),
            'approvedProposals' => $this->approvedProposalOptions($tenant->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->workData($request, $tenant->id);
        $proposal = $this->approvedProposal($data['quotation_proposal_id'] ?? null, $tenant->id, $data['condominium_id']);

        $work = DB::transaction(function () use ($data, $proposal, $request, $tenant) {
            $work = Work::create([
                'tenant_id' => $tenant->id,
                'created_by' => $request->user()?->id,
                ...$this->payload($data, $proposal),
            ]);

            $this->uploadAttachments($request, $work);

            return $work;
        });

        return redirect()->route('works.show', $work)->with('success', 'Obra/Reforma cadastrada.');
    }

    public function show(Request $request, Work $work): Response
    {
        $work = $this->authorizeWork($work);
        $work->load([
            'condominium:id,name',
            'supplier:id,name',
            'quotation:id,title',
            'quotationProposal:id,quotation_id,supplier_name,amount',
            'quotationProposal.quotation:id,title',
            'creator:id,name',
            'attachments:id,entity_id,original_filename,file_size_bytes,mime_type,created_at',
            'updates.author:id,name',
            'expenses' => fn ($q) => $q->with(['supplierRecord:id,name'])->orderBy('due_date'),
        ]);

        return Inertia::render('Works/Show', [
            'work' => $work,
            'types' => $this->typeOptions($work->tenant_id),
            'statuses' => Work::STATUSES,
            'priorities' => Work::PRIORITIES,
            'expensesTotal' => (float) $work->expenses()->sum('amount'),
            'openExpensesTotal' => (float) $work->expenses()->open()->sum('amount'),
            'canGenerateExpense' => $this->canGenerateExpense($request),
        ]);
    }

    public function edit(Work $work): Response
    {
        $work = $this->authorizeWork($work);
        $work->load('attachments:id,entity_id,original_filename,file_size_bytes,mime_type,created_at');

        return Inertia::render('Works/Edit', [
            'work' => $work,
            'types' => $this->typeOptions($work->tenant_id),
            'statuses' => Work::STATUSES,
            'priorities' => Work::PRIORITIES,
            'condominiums' => $this->condominiumOptions($work->tenant_id),
            'suppliers' => $this->supplierOptions($work->tenant_id),
            'approvedProposals' => $this->approvedProposalOptions($work->tenant_id, $work->quotation_proposal_id),
        ]);
    }

    public function update(Request $request, Work $work): RedirectResponse
    {
        $work = $this->authorizeWork($work);
        $data = $this->workData($request, $work->tenant_id);
        $proposal = $this->approvedProposal($data['quotation_proposal_id'] ?? null, $work->tenant_id, $data['condominium_id'], $work->id);

        DB::transaction(function () use ($work, $data, $proposal, $request) {
            $work->update($this->payload($data, $proposal, $work));
            $this->uploadAttachments($request, $work);
        });

        return redirect()->route('works.show', $work)->with('success', 'Obra/Reforma atualizada.');
    }

    public function destroy(Work $work): RedirectResponse
    {
        $work = $this->authorizeWork($work);

        foreach ($work->attachments as $attachment) {
            $this->storage->delete($attachment);
        }

        $work->delete();

        return redirect()->route('works.index')->with('success', 'Obra/Reforma removida.');
    }

    public function storeUpdate(Request $request, Work $work): RedirectResponse
    {
        $work = $this->authorizeWork($work);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'status' => ['nullable', Rule::in(array_keys(Work::STATUSES))],
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'occurred_at' => 'nullable|date',
        ]);

        DB::transaction(function () use ($work, $data, $request) {
            $work->updates()->create([
                'tenant_id' => $work->tenant_id,
                'user_id' => $request->user()?->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'progress_percent' => $data['progress_percent'] ?? null,
                'occurred_at' => isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
            ]);

            $changes = [];
            if (! empty($data['status'])) {
                $changes['status'] = $data['status'];
            }
            if (array_key_exists('progress_percent', $data) && $data['progress_percent'] !== null) {
                $changes['progress_percent'] = (int) $data['progress_percent'];
            }

            if (($changes['status'] ?? null) === 'completed') {
                $changes['progress_percent'] = 100;
                $changes['completed_at'] = $work->completed_at ?? now();
            } elseif (array_key_exists('status', $changes) && $changes['status'] !== 'completed') {
                $changes['completed_at'] = null;
            }

            if ($changes !== []) {
                $work->update($changes);
            }
        });

        return back()->with('success', 'Andamento registrado.');
    }

    public function storeExpense(Request $request, Work $work): RedirectResponse
    {
        $work = $this->authorizeWork($work);
        abort_unless($this->canGenerateExpense($request), 403);

        $data = $request->validate([
            'description' => 'nullable|string|max:200',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'document_number' => 'nullable|string|max:80',
            'reminder_days' => 'nullable|integer|min:0|max:60',
            'notes' => 'nullable|string|max:1000',
        ]);

        Expense::create([
            'tenant_id' => $work->tenant_id,
            'condominium_id' => $work->condominium_id,
            'category' => 'maintenance',
            'description' => $data['description'] ?: "Obra/Reforma: {$work->title}",
            'amount' => $data['amount'],
            'status' => 'pending',
            'expense_date' => today(),
            'due_date' => Carbon::parse($data['due_date']),
            'supplier' => $work->supplier?->name,
            'supplier_id' => $work->supplier_id,
            'document_number' => $data['document_number'] ?? null,
            'reminder_days' => $data['reminder_days'] ?? 3,
            'work_id' => $work->id,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Conta a pagar vinculada à obra.');
    }

    private function workData(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'supplier_id' => ['nullable', 'uuid', "exists:suppliers,id,tenant_id,{$tenantId}"],
            'quotation_proposal_id' => ['nullable', 'uuid', "exists:quotation_proposals,id,tenant_id,{$tenantId}"],
            'title' => 'required|string|max:160',
            'type' => ['required', Rule::in(array_keys($this->typeOptions($tenantId)))],
            'status' => ['required', Rule::in(array_keys(Work::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Work::PRIORITIES))],
            'description' => 'nullable|string|max:5000',
            'start_date' => 'nullable|date',
            'expected_end_date' => 'nullable|date',
            'budget_amount' => 'nullable|numeric|min:0',
            'final_amount' => 'nullable|numeric|min:0',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'responsible_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        ]);
    }

    private function payload(array $data, ?QuotationProposal $proposal, ?Work $work = null): array
    {
        $status = $data['status'];
        $completed = $status === 'completed';

        return [
            'condominium_id' => $data['condominium_id'],
            'supplier_id' => $data['supplier_id'] ?: $proposal?->supplier_id,
            'quotation_id' => $proposal?->quotation_id,
            'quotation_proposal_id' => $proposal?->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'status' => $status,
            'priority' => $data['priority'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'expected_end_date' => $data['expected_end_date'] ?? null,
            'completed_at' => $completed ? ($work?->completed_at ?? now()) : null,
            'budget_amount' => $data['budget_amount'] ?? $proposal?->amount,
            'final_amount' => $data['final_amount'] ?? null,
            'progress_percent' => $completed ? 100 : (int) ($data['progress_percent'] ?? $work?->progress_percent ?? 0),
            'responsible_name' => $data['responsible_name'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function approvedProposal(?string $proposalId, string $tenantId, string $condominiumId, ?string $currentWorkId = null): ?QuotationProposal
    {
        if (! $proposalId) {
            return null;
        }

        $proposal = QuotationProposal::with(['quotation:id,tenant_id,condominium_id,title'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->find($proposalId);

        if (! $proposal) {
            throw ValidationException::withMessages([
                'quotation_proposal_id' => 'Selecione uma proposta aprovada.',
            ]);
        }

        if ($proposal->quotation?->condominium_id !== $condominiumId) {
            throw ValidationException::withMessages([
                'quotation_proposal_id' => 'A proposta aprovada pertence a outro condomínio.',
            ]);
        }

        $linkedWork = Work::where('tenant_id', $tenantId)
            ->where('quotation_proposal_id', $proposal->id)
            ->when($currentWorkId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($linkedWork) {
            throw ValidationException::withMessages([
                'quotation_proposal_id' => 'Esta proposta já está vinculada a outra obra/reforma.',
            ]);
        }

        return $proposal;
    }

    private function uploadAttachments(Request $request, Work $work): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $this->storage->upload(
                file: $file,
                tenant: app('tenant'),
                entityType: Work::ATTACHMENT_ENTITY,
                entityId: $work->id,
                visibility: 'tenant',
                condominiumId: $work->condominium_id,
            );
        }
    }

    private function summary(string $tenantId): array
    {
        return [
            'active' => Work::where('tenant_id', $tenantId)->whereIn('status', ['planned', 'budgeting', 'approved', 'in_progress', 'paused'])->count(),
            'in_progress' => Work::where('tenant_id', $tenantId)->where('status', 'in_progress')->count(),
            'completed' => Work::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
            'budget_total' => (float) Work::where('tenant_id', $tenantId)->whereNotIn('status', ['cancelled'])->sum('budget_amount'),
            'open_expenses_total' => (float) Expense::where('tenant_id', $tenantId)->whereNotNull('work_id')->open()->sum('amount'),
        ];
    }

    private function typeOptions(string $tenantId): array
    {
        return Category::optionsFor($tenantId, 'work', Work::TYPES);
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

    private function approvedProposalOptions(string $tenantId, ?string $selectedId = null): \Illuminate\Support\Collection
    {
        return QuotationProposal::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->with(['quotation:id,title,condominium_id', 'supplier:id,name'])
            ->where(function ($query) use ($selectedId) {
                $query->whereDoesntHave('work');
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->latest('submitted_at')
            ->get(['id', 'quotation_id', 'supplier_id', 'supplier_name', 'amount'])
            ->map(fn (QuotationProposal $proposal) => [
                'value' => $proposal->id,
                'label' => trim(implode(' · ', array_filter([
                    $proposal->quotation?->title,
                    $proposal->supplier?->name ?? $proposal->supplier_name,
                    'R$ '.number_format((float) $proposal->amount, 2, ',', '.'),
                ]))),
                'condominium_id' => $proposal->quotation?->condominium_id,
                'supplier_id' => $proposal->supplier_id,
                'amount' => (string) $proposal->amount,
            ]);
    }

    private function canGenerateExpense(Request $request): bool
    {
        return $request->user()?->hasPermission('expenses:create')
            && $this->planAllows('financial', $request);
    }

    private function planAllows(string $module, Request $request): bool
    {
        if ($request->user()?->isSuperAdmin()) {
            return true;
        }

        return (bool) app('tenant')->activePlan()?->hasModule($module);
    }

    private function authorizeWork(Work $work): Work
    {
        abort_unless($work->tenant_id === app('tenant')->id, 403);

        return $work;
    }
}
