<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\Unit;
use App\Models\User;
use App\Services\OccurrenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OccurrenceController extends Controller
{
    use InteractsWithAttachments;

    public function __construct(
        private readonly OccurrenceService $service,
        private readonly \App\Services\AI\AssistantService $assistant,
    ) {
    }

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $occurrences = Occurrence::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'unit:id,number', 'assignee:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->sla === 'overdue', fn ($q) => $q->where('status', '!=', 'closed')->where('due_at', '<', now()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Occurrences/Index', [
            'occurrences' => $occurrences,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => $this->categoryOptions($tenant->id),
            'priorities' => Occurrence::PRIORITIES,
            'statuses' => Occurrence::STATUSES,
            'filters' => $request->only(['search', 'status', 'category', 'priority', 'condominium_id', 'sla']),
        ]);
    }

    /** Painel de chamados: estatísticas agregadas de ocorrências do tenant. */
    public function dashboard(Request $request): Response
    {
        $tenant = app('tenant');
        $base = fn () => Occurrence::where('tenant_id', $tenant->id)
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id));

        $byStatus = $base()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $overdue = $base()->where('status', '!=', 'closed')->where('due_at', '<', now())->count();

        $byPriority = $base()->where('status', '!=', 'closed')
            ->select('priority', DB::raw('count(*) as total'))->groupBy('priority')->pluck('total', 'priority');

        $byCategory = $base()->where('status', '!=', 'closed')
            ->select('category', DB::raw('count(*) as total'))->groupBy('category')
            ->orderByDesc('total')->pluck('total', 'category');

        $avgResolutionHours = $base()->whereNotNull('closed_at')
            ->value(DB::raw('avg(extract(epoch from (closed_at - created_at)) / 3600)'));

        $avgFirstResponseHours = $base()->whereNotNull('first_response_at')
            ->value(DB::raw('avg(extract(epoch from (first_response_at - created_at)) / 3600)'));

        $byAssignee = $base()->where('status', '!=', 'closed')->whereNotNull('assigned_to')
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')->orderByDesc('total')->limit(8)->get();
        $names = User::whereIn('id', $byAssignee->pluck('assigned_to'))->pluck('name', 'id');

        $categories = $this->categoryOptions($tenant->id);

        return Inertia::render('Occurrences/Dashboard', [
            'stats' => [
                'byStatus' => $byStatus,
                'overdue' => $overdue,
                'byPriority' => $byPriority,
                'byCategory' => $byCategory->mapWithKeys(fn ($total, $cat) => [($categories[$cat] ?? $cat) => $total]),
                'avgResolutionHours' => $avgResolutionHours ? round((float) $avgResolutionHours, 1) : null,
                'avgFirstResponseHours' => $avgFirstResponseHours ? round((float) $avgFirstResponseHours, 1) : null,
                'byAssignee' => $byAssignee->map(fn ($r) => ['name' => $names[$r->assigned_to] ?? '—', 'total' => $r->total]),
            ],
            'statuses' => Occurrence::STATUSES,
            'priorities' => Occurrence::PRIORITIES,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'filters' => $request->only(['condominium_id']),
            'canConfigureSla' => Auth::user()->hasPermission('occurrences:update'),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Occurrences/Create', $this->formData($tenant->id));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        // O responsável é definido via serviço (registra no histórico e notifica).
        $assignee = $data['assigned_to'] ?? null;
        unset($data['assigned_to']);

        $occurrence = Occurrence::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'created_by' => Auth::id(),
            'status' => 'open',
        ]));

        if ($assignee) {
            $this->service->assign($occurrence, $assignee);
        }

        // Prazo automático pelo SLA da prioridade quando não informado manualmente.
        $this->service->ensureDueAt($occurrence);

        $request->validate($this->attachmentRules());
        try {
            $this->storeAttachments($request, $occurrence, Occurrence::ATTACHMENT_ENTITY, 'tenant', $occurrence->condominium_id);
        } catch (StorageQuotaException $e) {
            return redirect()->route('occurrences.show', $occurrence)->with('error', $e->getMessage());
        }

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência registrada.');
    }

    public function show(Occurrence $occurrence): Response
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $occurrence->load([
            'condominium:id,name', 'unit:id,number', 'creator:id,name', 'assignee:id,name',
            'comments.user:id,name', 'attachments',
        ]);

        $tenant = app('tenant');

        return Inertia::render('Occurrences/Show', [
            'occurrence' => $occurrence,
            'attachments' => $occurrence->attachmentsPayload(),
            'assignableUsers' => $this->userOptions($tenant->id),
            'categories' => $this->categoryOptions($tenant->id),
            'priorities' => Occurrence::PRIORITIES,
            'statuses' => Occurrence::STATUSES,
            'canClose' => Auth::user()->hasPermission('occurrences:close'),
            'canUpdate' => Auth::user()->hasPermission('occurrences:update'),
            'canDraftAi' => Auth::user()->hasPermission('ai:use') && $this->assistant->configured(),
        ]);
    }

    public function edit(Occurrence $occurrence): Response
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $tenant = app('tenant');

        return Inertia::render('Occurrences/Edit', array_merge(
            ['occurrence' => $occurrence],
            $this->formData($tenant->id),
        ));
    }

    public function update(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $data = $this->validated($request, $occurrence->tenant_id);

        // A mudança de responsável passa pelo serviço (registra no histórico e notifica),
        // não pelo update em massa.
        $newAssignee = $data['assigned_to'] ?? null;
        unset($data['assigned_to']);
        $oldAssignee = $occurrence->assigned_to;
        $oldPriority = $occurrence->priority;

        // Se a prioridade mudou e o prazo não foi informado manualmente, recalcula pelo SLA.
        if (empty($data['due_at']) && ($data['priority'] ?? $oldPriority) !== $oldPriority) {
            $data['due_at'] = ($occurrence->created_at ?? now())
                ->copy()->addDays($this->service->slaDaysFor($occurrence->tenant_id, $data['priority']));
        }

        $occurrence->update($data);

        if ($newAssignee !== $oldAssignee) {
            $this->service->assign($occurrence, $newAssignee);
        }

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência atualizada.');
    }

    public function destroy(Occurrence $occurrence): RedirectResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $occurrence->delete();

        return redirect()->route('occurrences.index')->with('success', 'Ocorrência removida.');
    }

    public function changeStatus(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Occurrence::STATUSES)),
        ]);

        // Encerrar exige a permissão específica occurrences:close.
        if ($data['status'] === 'closed') {
            abort_unless(Auth::user()->hasPermission('occurrences:close'), 403);
        }

        $this->service->changeStatus($occurrence, $data['status']);

        return back()->with('success', 'Status atualizado.');
    }

    public function assign(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'assigned_to' => "nullable|uuid|exists:users,id,tenant_id,{$occurrence->tenant_id}",
        ]);

        $this->service->assign($occurrence, $data['assigned_to'] ?? null);

        return back()->with('success', 'Responsável atualizado.');
    }

    public function addComment(Request $request, Occurrence $occurrence): RedirectResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ]);

        $this->service->addComment($occurrence, $data['body'], $data['is_internal'] ?? false);

        return back()->with('success', 'Comentário adicionado.');
    }

    /** Sugere, via IA, um texto de resposta para a ocorrência (consumido por fetch na tela). */
    public function draftReply(Occurrence $occurrence): \Illuminate\Http\JsonResponse
    {
        $occurrence = $this->authorizeTenant($occurrence);

        if (! $this->assistant->configured()) {
            return response()->json(['message' => 'O assistente de IA não está configurado.'], 422);
        }

        try {
            $text = $this->assistant->draftOccurrenceReply(app('tenant'), $occurrence);
        } catch (\App\Services\AI\AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['text' => $text]);
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'unit_id' => "nullable|uuid|exists:units,id,tenant_id,{$tenantId}",
            'assigned_to' => "nullable|uuid|exists:users,id,tenant_id,{$tenantId}",
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:'.implode(',', array_keys($this->categoryOptions($tenantId))),
            'priority' => 'required|in:'.implode(',', array_keys(Occurrence::PRIORITIES)),
            'due_at' => 'nullable|date',
        ]);
    }

    private function formData(string $tenantId): array
    {
        return [
            'condominiums' => $this->condominiumOptions($tenantId),
            'units' => Unit::where('tenant_id', $tenantId)
                ->orderBy('number')
                ->get(['id', 'condominium_id', 'number'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->number, 'condominium_id' => $u->condominium_id]),
            'assignableUsers' => $this->userOptions($tenantId),
            'categories' => $this->categoryOptions($tenantId),
            'priorities' => Occurrence::PRIORITIES,
        ];
    }

    /** Categorias padrão + customizadas (ativas) do tenant. */
    private function categoryOptions(string $tenantId): array
    {
        return \App\Models\Category::optionsFor($tenantId, 'occurrence', Occurrence::CATEGORIES);
    }

    private function authorizeTenant(Occurrence $occurrence): Occurrence
    {
        abort_unless($occurrence->tenant_id === app('tenant')->id, 403);

        return $occurrence;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    private function userOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return User::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]);
    }
}
