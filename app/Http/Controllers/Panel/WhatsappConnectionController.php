<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use App\Services\WhatsappConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Área "Conexão do WhatsApp" (síndico): cria conexões até o limite licenciado, pareia via QR,
 * acompanha o status e aloca quais condomínios cada conexão atende. Uma conexão = uma instância
 * Evolution = um número. Permissão settings:whatsapp.
 */
class WhatsappConnectionController extends Controller
{
    public function __construct(
        private readonly EvolutionManager $evolution,
        private readonly WhatsappConnectionService $licensing,
    ) {}

    public function index(): Response
    {
        $tenant = app('tenant');

        $connections = WhatsappConnection::where('tenant_id', $tenant->id)
            ->with('condominiums:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (WhatsappConnection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone_number' => $c->phone_number,
                'status' => $c->status,
                'status_label' => WhatsappConnection::STATUSES[$c->status] ?? $c->status,
                'bot_enabled' => $c->bot_enabled,
                'condominium_ids' => $c->condominiums->pluck('id'),
                'condominiums' => $c->condominiums->pluck('name'),
            ]);

        return Inertia::render('Settings/WhatsappConnections', [
            'connections' => $connections,
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'usage' => [
                'used' => $this->licensing->used($tenant),
                'limit' => $this->licensing->limit($tenant),
            ],
            'evolutionConfigured' => $this->evolution->isConfigured(),
            'statuses' => WhatsappConnection::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate(['name' => 'required|string|max:80']);

        if (! $this->evolution->isConfigured()) {
            return back()->with('error', 'Servidor Evolution não configurado. Contate o suporte.');
        }

        // Licença: bloqueia acima do contratado (plano + add-ons) → 402 tratado globalmente.
        $this->licensing->assertCanCreate($tenant);

        $instance = $this->uniqueInstanceName($tenant->slug ?? 'tenant');

        $payload = $this->evolution->createInstance($instance, config('services.evolution.webhook_url'));
        $token = $payload['hash'] ?? $payload['instance']['apikey'] ?? $payload['apikey'] ?? null;

        WhatsappConnection::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'instance' => $instance,
            'token' => $token,
            'status' => 'connecting',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Conexão criada. Leia o QR Code para parear o número.');
    }

    /** QR Code / código de pareamento (consumido via fetch pelo modal, com polling). */
    public function connect(WhatsappConnection $connection): JsonResponse
    {
        $this->authorizeTenant($connection);

        $result = $this->evolution->connect($connection->instance);

        return response()->json([
            'base64' => $result['base64'] ?? null,
            'code' => $result['code'] ?? ($result['pairingCode'] ?? null),
        ]);
    }

    /** Estado da conexão; atualiza o registro e devolve o status normalizado. */
    public function state(WhatsappConnection $connection): JsonResponse
    {
        $this->authorizeTenant($connection);

        $result = $this->evolution->connectionState($connection->instance);
        $state = $result['instance']['state'] ?? $result['state'] ?? null;

        $status = match ($state) {
            'open' => 'connected',
            'connecting' => 'connecting',
            default => 'disconnected',
        };

        $connection->update([
            'status' => $status,
            'last_connected_at' => $status === 'connected' ? now() : $connection->last_connected_at,
        ]);

        return response()->json(['status' => $status, 'status_label' => WhatsappConnection::STATUSES[$status]]);
    }

    /** Aloca quais condomínios a conexão atende (N:N). */
    public function syncCondominiums(Request $request, WhatsappConnection $connection): RedirectResponse
    {
        $this->authorizeTenant($connection);
        $tenant = app('tenant');

        $data = $request->validate([
            'condominium_ids' => 'array',
            'condominium_ids.*' => 'uuid',
        ]);

        // Garante que todos os condomínios são do tenant.
        $valid = Condominium::where('tenant_id', $tenant->id)
            ->whereIn('id', $data['condominium_ids'] ?? [])
            ->pluck('id');

        $connection->condominiums()->sync($valid);

        return back()->with('success', 'Condomínios atualizados.');
    }

    public function destroy(WhatsappConnection $connection): RedirectResponse
    {
        $this->authorizeTenant($connection);

        // Tenta remover na Evolution (logout + delete); falha não impede a remoção local.
        try {
            $this->evolution->logout($connection->instance);
            $this->evolution->deleteInstance($connection->instance);
        } catch (\Throwable) {
            // ignora — instância pode já não existir no servidor
        }

        $connection->delete();

        return back()->with('success', 'Conexão removida.');
    }

    private function authorizeTenant(WhatsappConnection $connection): void
    {
        abort_unless($connection->tenant_id === app('tenant')->id, 403);
    }

    private function uniqueInstanceName(string $base): string
    {
        do {
            $name = Str::slug($base).'-'.Str::lower(Str::random(6));
        } while (WhatsappConnection::withTrashed()->where('instance', $name)->exists());

        return $name;
    }
}
