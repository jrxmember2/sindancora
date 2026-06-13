<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Comunicados para o app do síndico. Espelha Panel\AnnouncementController
 * (mesmas validações e AnnouncementService::publish), com payload JSON.
 */
class AnnouncementController extends AppController
{
    use InteractsWithAttachments;

    public function __construct(private readonly AnnouncementService $service) {}

    #[OA\Get(
        path: '/v1/app/announcements',
        operationId: 'appAnnouncementsIndex',
        summary: 'Listar comunicados (filtros: search, status, condominium_id)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada')],
    )]
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::where('tenant_id', $this->tenant()->id)
            ->select(['id', 'condominium_id', 'title', 'category', 'urgency', 'status', 'published_at', 'publish_at', 'expires_at', 'created_at'])
            ->with('condominium:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'success' => true,
            'data' => $announcements->getCollection()->map(fn (Announcement $a) => $this->payload($a)),
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
                'last_page' => $announcements->lastPage(),
            ],
            'options' => [
                'categories' => Announcement::CATEGORIES,
                'urgencies' => Announcement::URGENCIES,
                'condominiums' => $this->condominiumOptions(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/v1/app/announcements/{id}',
        operationId: 'appAnnouncementsShow',
        summary: 'Detalhe do comunicado com anexos',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Comunicado')],
    )]
    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorizeTenant($announcement);
        $announcement->load('condominium:id,name', 'creator:id,name');

        return $this->ok($this->payload($announcement, full: true));
    }

    #[OA\Post(
        path: '/v1/app/announcements',
        operationId: 'appAnnouncementsStore',
        summary: 'Criar comunicado (action: draft|publish; anexos opcionais)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        responses: [new OA\Response(response: 201, description: 'Comunicado criado')],
    )]
    public function store(Request $request): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $this->validated($request, $tenant->id);
        $action = $request->input('action', 'draft');

        $announcement = Announcement::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'created_by' => Auth::id(),
            'status' => 'draft',
        ]));

        $request->validate($this->attachmentRules());
        try {
            $this->storeAttachments($request, $announcement, Announcement::ATTACHMENT_ENTITY, 'public_to_residents', $announcement->condominium_id);
        } catch (StorageQuotaException $e) {
            // O comunicado fica salvo como rascunho; informa o estouro de cota.
            return response()->json([
                'success' => false,
                'error' => ['code' => 'STORAGE_QUOTA', 'message' => $e->getMessage()],
                'data' => ['id' => $announcement->id],
            ], 422);
        }

        // Publica agora apenas quando solicitado e sem agendamento futuro (mesma regra do painel).
        if ($action === 'publish' && ! ($announcement->publish_at && $announcement->publish_at->isFuture())) {
            $this->service->publish($announcement);
        }

        return $this->ok($this->payload($announcement->refresh(), full: true), 201);
    }

    #[OA\Post(
        path: '/v1/app/announcements/{id}/publish',
        operationId: 'appAnnouncementsPublish',
        summary: 'Publicar comunicado (rascunho ou agendado)',
        security: [['bearerAuth' => []]],
        tags: ['App'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Comunicado publicado')],
    )]
    public function publish(Announcement $announcement): JsonResponse
    {
        $this->authorizeTenant($announcement);
        $this->service->publish($announcement);

        return $this->ok($this->payload($announcement->refresh(), full: true));
    }

    /** Mesmas regras de validação do painel (Panel\AnnouncementController). */
    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'title' => 'required|string|max:200',
            'body' => 'required|string',
            'category' => 'required|in:'.implode(',', array_keys(Announcement::CATEGORIES)),
            'urgency' => 'required|in:'.implode(',', array_keys(Announcement::URGENCIES)),
            'publish_at' => 'nullable|date',
            'expires_at' => $request->filled('publish_at')
                ? 'nullable|date|after:publish_at'
                : 'nullable|date',
        ]);
    }

    private function payload(Announcement $a, bool $full = false): array
    {
        $base = [
            'id' => $a->id,
            'title' => $a->title,
            'category' => $a->category,
            'category_label' => Announcement::CATEGORIES[$a->category] ?? $a->category,
            'urgency' => $a->urgency,
            'urgency_label' => Announcement::URGENCIES[$a->urgency] ?? $a->urgency,
            'status' => $a->status,
            'condominium' => $a->condominium ? ['id' => $a->condominium->id, 'name' => $a->condominium->name] : null,
            'published_at' => $a->published_at?->toIso8601String(),
            'publish_at' => $a->publish_at?->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
        ];

        if ($full) {
            $base['body'] = $a->body;
            $base['creator'] = $a->creator ? ['id' => $a->creator->id, 'name' => $a->creator->name] : null;
            $base['attachments'] = $a->attachmentsPayload();
        }

        return $base;
    }
}
