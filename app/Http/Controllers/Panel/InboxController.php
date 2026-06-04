<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\WaConversation;
use App\Services\Whatsapp\EvolutionManager;
use App\Services\WaInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbox de WhatsApp (Fase 2): lista de conversas + thread + responder. Escopo por condomínio
 * (filtro) e atribuição. Atualização ao vivo por polling. Setores/chatbot ficam na Fase 3.
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

        $conversations = WaConversation::where('tenant_id', $tenant->id)
            ->with(['connection:id,name', 'condominium:id,name', 'assignee:id,name'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
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
                'assignee' => $c->assignee?->name,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        $selected = null;
        if ($request->conversation) {
            $conversation = WaConversation::where('tenant_id', $tenant->id)
                ->with(['connection:id,name,status', 'condominium:id,name', 'assignee:id,name'])
                ->find($request->conversation);

            if ($conversation) {
                $this->inbox->markRead($conversation);

                $selected = [
                    'id' => $conversation->id,
                    'contact_name' => $conversation->contact_name,
                    'contact_phone' => $conversation->contact_phone,
                    'status' => $conversation->status,
                    'connection' => $conversation->connection?->name,
                    'connection_status' => $conversation->connection?->status,
                    'condominium' => $conversation->condominium?->name,
                    'assignee' => $conversation->assignee?->name,
                    'assigned_to_me' => $conversation->assigned_to === Auth::id(),
                    'messages' => $conversation->messages()->limit(200)->get()->map(fn ($m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
                ];
            }
        }

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'selected' => $selected,
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'filters' => [
                'condominium_id' => $request->condominium_id,
                'status' => $request->status ?? 'open',
                'conversation' => $request->conversation,
            ],
        ]);
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
    }
}
