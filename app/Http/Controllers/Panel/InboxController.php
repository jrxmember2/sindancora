<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Sector;
use App\Models\WaConversation;
use App\Services\Whatsapp\EvolutionManager;
use App\Services\WaInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbox de WhatsApp: lista de conversas + thread + responder. Atualização ao vivo por polling.
 * Escopo por setor (Fase 3): quem não gerencia setores (sectors:manage) só vê conversas dos seus
 * setores; síndico/admin veem tudo. Filtros adicionais por condomínio e status.
 */
class InboxController extends Controller
{
    public function __construct(
        private readonly WaInboxService $inbox,
        private readonly EvolutionManager $evolution,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $user = Auth::user();
        $seeAll = $user->hasPermission('sectors:manage');
        $mySectorIds = $seeAll ? null : $user->sectors()->pluck('sectors.id');

        $conversations = WaConversation::where('tenant_id', $tenant->id)
            ->with(['connection:id,name', 'condominium:id,name', 'sector:id,name', 'assignee:id,name'])
            ->when(! $seeAll, fn ($q) => $q->whereIn('sector_id', $mySectorIds))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->sector_id, fn ($q, $id) => $q->where('sector_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'open'))
            ->orderByDesc('last_message_at')
            ->limit(80)
            ->get()
            ->map(fn (WaConversation $c) => [
                'id' => $c->id,
                'contact_name' => $c->contact_name,
                'contact_phone' => $c->contact_phone,
                'status' => $c->status,
                'unread_count' => $c->unread_count,
                'connection' => $c->connection?->name,
                'condominium' => $c->condominium?->name,
                'sector' => $c->sector?->name,
                'assignee' => $c->assignee?->name,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        $selected = null;
        if ($request->conversation) {
            $conversation = WaConversation::where('tenant_id', $tenant->id)
                ->with(['connection:id,name,status', 'condominium:id,name', 'sector:id,name', 'assignee:id,name'])
                ->find($request->conversation);

            if ($conversation && $this->canAccess($conversation, $seeAll, $mySectorIds)) {
                $this->inbox->markRead($conversation);

                $selected = [
                    'id' => $conversation->id,
                    'contact_name' => $conversation->contact_name,
                    'contact_phone' => $conversation->contact_phone,
                    'status' => $conversation->status,
                    'connection' => $conversation->connection?->name,
                    'connection_status' => $conversation->connection?->status,
                    'condominium' => $conversation->condominium?->name,
                    'sector' => $conversation->sector?->name,
                    'assignee' => $conversation->assignee?->name,
                    'assigned_to_me' => $conversation->assigned_to === Auth::id(),
                    'messages' => $conversation->messages()->limit(200)->get()->map(fn ($m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'is_bot' => $m->direction === 'out' && $m->sent_by === null,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
                ];
            }
        }

        // Setores disponíveis no filtro: todos do tenant (gestor) ou só os do atendente.
        $sectorsQuery = Sector::where('tenant_id', $tenant->id)->active();
        if (! $seeAll) {
            $sectorsQuery->whereIn('id', $mySectorIds);
        }

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'selected' => $selected,
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'sectors' => $sectorsQuery->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]),
            'filters' => [
                'condominium_id' => $request->condominium_id,
                'sector_id' => $request->sector_id,
                'status' => $request->status ?? 'open',
                'conversation' => $request->conversation,
            ],
        ]);
    }

    /** Atendente sem sectors:manage só acessa conversas dos seus setores. */
    private function canAccess(WaConversation $conversation, bool $seeAll, $mySectorIds): bool
    {
        return $seeAll || ($conversation->sector_id && $mySectorIds->contains($conversation->sector_id));
    }

    public function send(Request $request, WaConversation $conversation): RedirectResponse
    {
        $this->authorizeTenant($conversation);
        $data = $request->validate(['body' => 'required|string|max:4000']);

        $connection = $conversation->connection;
        if (! $connection) {
            return back()->with('error', 'Conexão indisponível.');
        }

        $payload = $this->evolution->sendText($connection, $conversation->contact_phone, $data['body']);

        if ($payload === null) {
            return back()->with('error', 'Não foi possível enviar. Verifique se o número está conectado.');
        }

        $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;
        $this->inbox->recordOutbound($conversation, $data['body'], $waId, Auth::id());

        return back(303);
    }

    public function assign(WaConversation $conversation): RedirectResponse
    {
        $this->authorizeTenant($conversation);

        $conversation->update([
            'assigned_to' => $conversation->assigned_to === Auth::id() ? null : Auth::id(),
        ]);

        return back(303);
    }

    public function toggleStatus(WaConversation $conversation): RedirectResponse
    {
        $this->authorizeTenant($conversation);

        $conversation->update(['status' => $conversation->status === 'open' ? 'closed' : 'open']);

        return back(303);
    }

    private function authorizeTenant(WaConversation $conversation): void
    {
        abort_unless($conversation->tenant_id === app('tenant')->id, 403);

        $user = Auth::user();
        if ($user->hasPermission('sectors:manage')) {
            return; // gestor vê/atende tudo
        }

        $mine = $user->sectors()->pluck('sectors.id');
        abort_unless($conversation->sector_id && $mine->contains($conversation->sector_id), 403);
    }
}
