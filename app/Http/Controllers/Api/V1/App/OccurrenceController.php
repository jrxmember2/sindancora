<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Models\Category;
use App\Models\Occurrence;
use App\Services\OccurrenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Ocorrências/chamados para o app do síndico. Espelha Panel\OccurrenceController:
 * mesmas validações; mudanças de status/responsável/comentários SEMPRE via
 * OccurrenceService (histórico + notificações).
 */
class OccurrenceController extends AppController
{
    use InteractsWithAttachments;

    public function __construct(private readonly OccurrenceService $service) {}

    #[OA\Get(
        path: '/v1/app/occurrences',
        operationId: 'appOccurrencesIndex',
        summary: 'Listar ocorrências (filtros: search, status, category, priority, condominium_id, sla=overdue)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada')],
    )]
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        $occurrences = Occurrence::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'unit:id,number', 'assignee:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->sla === 'overdue', fn ($q) => $q->where('status', '!=', 'closed')->where('due_at', '<', now()))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'success' => true,
            'data' => $occurrences->getCollection()->map(fn (Occurrence $o) => $this->payload($o)),
            'meta' => [
                'current_page' => $occurrences->currentPage(),
                'per_page' => $occurrences->perPage(),
                'total' => $occurrences->total(),
                'last_page' => $occurrences->lastPage(),
            ],
            'options' => [
                'categories' => $this->categoryOptions($tenant->id),
                'priorities' => Occurrence::PRIORITIES,
                'statuses' => Occurrence::STATUSES,
                'condominiums' => $this->condominiumOptions(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/v1/app/occurrences/{id}',
        operationId: 'appOccurrencesShow',
        summary: 'Detalhe da ocorrência com histórico e anexos',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Ocorrência')],
    )]
    public function show(Occurrence $occurrence): JsonResponse
    {
        $this->authorizeTenant($occurrence);
        $occurrence->load([
            'condominium:id,name', 'unit:id,number', 'creator:id,name', 'assignee:id,name',
            'comments.user:id,name',
        ]);

        return $this->ok($this->payload($occurrence, full: true));
    }

    #[OA\Post(
        path: '/v1/app/occurrences',
        operationId: 'appOccurrencesStore',
        summary: 'Abrir ocorrência (fotos/anexos opcionais no campo attachments[])',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 201, description: 'Ocorrência criada')],
    )]
    public function store(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $this->validated($request, $tenant->id);

        // O responsável é definido via serviço (registra histórico e notifica).
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

        // Prazo automático pelo SLA da prioridade quando não informado.
        $this->service->ensureDueAt($occurrence);

        $request->validate($this->attachmentRules());
        $quotaError = null;
        try {
            $this->storeAttachments($request, $occurrence, Occurrence::ATTACHMENT_ENTITY, 'tenant', $occurrence->condominium_id);
        } catch (StorageQuotaException $e) {
            // A ocorrência fica registrada; informa que os anexos não subiram.
            $quotaError = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($occurrence->refresh(), full: true),
            'warning' => $quotaError,
        ], 201);
    }

    #[OA\Post(
        path: '/v1/app/occurrences/{id}/comments',
        operationId: 'appOccurrencesComment',
        summary: 'Comentar na ocorrência',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 201, description: 'Comentário criado')],
    )]
    public function addComment(Request $request, Occurrence $occurrence): JsonResponse
    {
        $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ]);

        $comment = $this->service->addComment($occurrence, $data['body'], $data['is_internal'] ?? false);

        return $this->ok([
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
        ], 201);
    }

    #[OA\Post(
        path: '/v1/app/occurrences/{id}/status',
        operationId: 'appOccurrencesStatus',
        summary: 'Mudar status (encerrar exige occurrences:close)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Status atualizado')],
    )]
    public function changeStatus(Request $request, Occurrence $occurrence): JsonResponse
    {
        $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(Occurrence::STATUSES)),
        ]);

        if ($data['status'] === 'closed') {
            abort_unless(Auth::user()->hasPermission('occurrences:close'), 403);
        }

        $this->service->changeStatus($occurrence, $data['status']);

        return $this->ok($this->payload($occurrence->refresh()));
    }

    #[OA\Post(
        path: '/v1/app/occurrences/{id}/assign',
        operationId: 'appOccurrencesAssign',
        summary: 'Atribuir responsável',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Responsável atualizado')],
    )]
    public function assign(Request $request, Occurrence $occurrence): JsonResponse
    {
        $this->authorizeTenant($occurrence);
        $data = $request->validate([
            'assigned_to' => "nullable|uuid|exists:users,id,tenant_id,{$occurrence->tenant_id}",
        ]);

        $this->service->assign($occurrence, $data['assigned_to'] ?? null);

        return $this->ok($this->payload($occurrence->refresh()));
    }

    /** Mesmas regras de validação do painel (Panel\OccurrenceController). */
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

    /** Categorias padrão + customizadas (ativas) do tenant. */
    private function categoryOptions(string $tenantId): array
    {
        return Category::optionsFor($tenantId, 'occurrence', Occurrence::CATEGORIES);
    }

    private function payload(Occurrence $o, bool $full = false): array
    {
        $base = [
            'id' => $o->id,
            'title' => $o->title,
            'category' => $o->category,
            'priority' => $o->priority,
            'priority_label' => Occurrence::PRIORITIES[$o->priority] ?? $o->priority,
            'status' => $o->status,
            'status_label' => Occurrence::STATUSES[$o->status] ?? $o->status,
            'sla_status' => $o->sla_status,
            'due_at' => $o->due_at?->toIso8601String(),
            'condominium' => $o->condominium ? ['id' => $o->condominium->id, 'name' => $o->condominium->name] : null,
            'unit' => $o->unit ? ['id' => $o->unit->id, 'number' => $o->unit->number] : null,
            'assignee' => $o->assignee ? ['id' => $o->assignee->id, 'name' => $o->assignee->name] : null,
            'created_at' => $o->created_at?->toIso8601String(),
        ];

        if ($full) {
            $base['description'] = $o->description;
            $base['creator'] = $o->creator ? ['id' => $o->creator->id, 'name' => $o->creator->name] : null;
            $base['closed_at'] = $o->closed_at?->toIso8601String();
            $base['comments'] = $o->comments->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'body' => $c->body,
                'meta' => $c->meta,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);
            $base['attachments'] = $o->attachmentsPayload();
        }

        return $base;
    }
}
