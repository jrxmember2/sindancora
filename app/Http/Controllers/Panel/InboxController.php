<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Sector;
use App\Models\StorageObject;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WaOptOut;
use App\Models\WaQuickReply;
use App\Models\WhatsappConnection;
use App\Services\StorageService;
use App\Services\Whatsapp\EvolutionManager;
use App\Services\WaInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        private readonly StorageService $storage,
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
                    'messages' => $conversation->messages()->with('storageObject:id,original_filename,mime_type,deleted_at')->limit(200)->get()->map(fn (WaMessage $m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'is_bot' => $m->direction === 'out' && $m->sent_by === null,
                        'media' => $this->mediaPayload($m),
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
                ];
            }
        }

        // Respostas prontas que o atendente pode usar: globais (sem setor) + as dos seus setores.
        $quickReplies = WaQuickReply::where('tenant_id', $tenant->id)
            ->when(! $seeAll, fn ($q) => $q->where(fn ($w) => $w->whereNull('sector_id')->orWhereIn('sector_id', $mySectorIds)))
            ->orderBy('sort_order')->orderBy('title')
            ->get(['id', 'title', 'body', 'sector_id'])
            ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'body' => $r->body, 'sector_id' => $r->sector_id]);

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
            'connections' => WhatsappConnection::where('tenant_id', $tenant->id)->where('status', 'connected')
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'quickReplies' => $quickReplies,
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

    /** Serializa a mídia de uma mensagem (ou null). is_image controla a exibição inline. */
    private function mediaPayload(WaMessage $message): ?array
    {
        if (! $message->media_type) {
            return null;
        }

        $object = $message->storageObject;
        $available = $object && $object->deleted_at === null;

        return [
            'type' => $message->media_type,
            'name' => $object?->original_filename,
            'mime' => $object?->mime_type,
            'is_image' => $available && str_starts_with((string) $object->mime_type, 'image/'),
            'url' => $available ? route('inbox.media', $object->id) : null,
        ];
    }

    public function send(Request $request, WaConversation $conversation): RedirectResponse
    {
        $this->authorizeTenant($conversation);
        $data = $request->validate(['body' => 'required|string|max:4000']);

        $connection = $conversation->connection;
        if (! $connection) {
            return back()->with('error', 'Conexão indisponível.');
        }

        $body = $this->signed($data['body']);
        $payload = $this->evolution->sendText($connection, $conversation->contact_phone, $body);

        if ($payload === null) {
            return back()->with('error', 'Não foi possível enviar. Verifique se o número está conectado.');
        }

        $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;
        $this->inbox->recordOutbound($conversation, $body, $waId, Auth::id());

        return back(303);
    }

    /** Liga/desliga a assinatura das mensagens com o nome do atendente (preferência do usuário). */
    public function signature(Request $request): RedirectResponse
    {
        $request->validate(['enabled' => 'required|boolean']);
        Auth::user()->update(['sign_messages' => $request->boolean('enabled')]);

        return back(303);
    }

    /** Prefixa a mensagem com o nome do atendente quando a assinatura está ligada. */
    private function signed(?string $body): ?string
    {
        $user = Auth::user();
        if (! $user->sign_messages || blank($body)) {
            return $body;
        }

        return '*'.$user->name."*:\n".$body;
    }

    /** Inicia uma conversa nova: o atendente envia a 1ª mensagem para um número. */
    public function startConversation(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $user = Auth::user();

        $data = $request->validate([
            'connection_id' => 'required|uuid',
            'phone' => 'required|string|max:25',
            'body' => 'required|string|max:4000',
        ]);

        $connection = WhatsappConnection::where('tenant_id', $tenant->id)->find($data['connection_id']);
        if (! $connection || $connection->status !== 'connected') {
            return back()->with('error', 'Selecione uma conexão conectada.');
        }

        $phone = WaOptOut::normalizePhone($data['phone']);
        if (! $phone) {
            return back()->with('error', 'Telefone inválido.');
        }

        $conversation = WaConversation::where('tenant_id', $tenant->id)
            ->where('connection_id', $connection->id)
            ->where('contact_phone', $phone)
            ->first();

        if (! $conversation) {
            // Resolve o condomínio quando a conexão atende exatamente um.
            $condoIds = $connection->condominiums()->pluck('condominiums.id');

            // Atendente sem visão macro: amarra ao seu setor para conseguir ver a conversa.
            $sectorId = $user->hasPermission('sectors:manage') ? null : $user->sectors()->value('sectors.id');

            $conversation = WaConversation::create([
                'tenant_id' => $tenant->id,
                'connection_id' => $connection->id,
                'condominium_id' => $condoIds->count() === 1 ? $condoIds->first() : null,
                'sector_id' => $sectorId,
                'contact_phone' => $phone,
                'status' => 'open',
                'bot_state' => 'routed', // conversa iniciada por humano — não passa pelo chatbot
                'assigned_to' => $user->id,
                'unread_count' => 0,
            ]);
        }

        $body = $this->signed($data['body']);
        $payload = $this->evolution->sendText($connection, $phone, $body);
        if ($payload === null) {
            return back()->with('error', 'Não foi possível enviar. Verifique se o número está conectado.');
        }

        $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;
        $this->inbox->recordOutbound($conversation, $body, $waId, $user->id);

        return redirect()->route('inbox.index', ['conversation' => $conversation->id])->with('success', 'Conversa iniciada.');
    }

    /** Envia mídia (imagem/vídeo/documento) anexada pelo atendente. */
    public function sendMedia(Request $request, WaConversation $conversation): RedirectResponse
    {
        $this->authorizeTenant($conversation);

        $maxKb = config('services.evolution.media_max_mb') * 1024;
        $data = $request->validate([
            'file' => "required|file|max:{$maxKb}",
            'caption' => 'nullable|string|max:1000',
        ]);

        $connection = $conversation->connection;
        if (! $connection) {
            return back()->with('error', 'Conexão indisponível.');
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $mediatype = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'document');
        $contents = file_get_contents($file->getRealPath());
        $caption = $this->signed($data['caption'] ?? null);

        // Armazena primeiro (cota); StorageQuotaException → 402 tratado globalmente.
        $object = $this->storage->storeRaw(
            tenant: app('tenant'),
            entityType: 'wa_media',
            entityId: $conversation->id,
            contents: $contents,
            filename: $file->getClientOriginalName(),
            mimeType: $mime,
            visibility: 'tenant',
            condominiumId: $conversation->condominium_id,
        );

        $payload = $this->evolution->sendMedia(
            connection: $connection,
            number: $conversation->contact_phone,
            mediatype: $mediatype,
            mimetype: $mime,
            base64: base64_encode($contents),
            fileName: $file->getClientOriginalName(),
            caption: $caption,
        );

        if ($payload === null) {
            $this->storage->delete($object, immediate: true); // desfaz o armazenamento se o envio falhou
            return back()->with('error', 'Não foi possível enviar a mídia. Verifique se o número está conectado.');
        }

        $waId = $payload['key']['id'] ?? $payload['data']['key']['id'] ?? null;
        $this->inbox->recordOutbound($conversation, $caption, $waId, Auth::id(), $mediatype, $object->id);

        return back(303);
    }

    /** Redireciona para a URL assinada da mídia (escopo por tenant + setor da conversa). */
    public function media(StorageObject $object): RedirectResponse|StreamedResponse
    {
        abort_unless($object->tenant_id === app('tenant')->id && $object->entity_type === 'wa_media', 403);
        abort_if($object->deleted_at !== null, 404);

        $conversation = WaConversation::where('tenant_id', $object->tenant_id)->find($object->entity_id);
        abort_unless($conversation !== null, 404);
        $this->authorizeTenant($conversation); // mesma regra de escopo por setor

        // Drive externo: arquivo privado, sem URL pública → servimos por proxy autenticado (inline).
        if ($object->storage_provider === StorageService::PROVIDER_GOOGLE_DRIVE) {
            $contents = $this->storage->getContents($object);

            return new StreamedResponse(fn () => print($contents), 200, [
                'Content-Type' => $object->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes((string) $object->original_filename).'"',
                'Content-Length' => (string) strlen($contents),
            ]);
        }

        $disk = Storage::disk($object->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($object->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
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
