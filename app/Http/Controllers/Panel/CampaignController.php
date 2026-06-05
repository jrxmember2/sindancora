<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Condominium;
use App\Models\Unit;
use App\Models\WaCampaign;
use App\Models\WaOptOut;
use App\Models\WhatsappConnection;
use App\Services\StorageService;
use App\Services\WaCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Disparo em massa por WhatsApp (Fase 6): campanhas para moradores de um condomínio, segmentadas
 * por bloco/unidade, com throttle (anti-ban), opt-out e mídia opcional. Permissão campaigns:manage.
 */
class CampaignController extends Controller
{
    public function __construct(
        private readonly WaCampaignService $campaigns,
        private readonly StorageService $storage,
    ) {}

    public function index(): Response
    {
        $tenant = app('tenant');

        $campaigns = WaCampaign::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'connection:id,name'])
            ->orderByDesc('created_at')->limit(100)->get()
            ->map(fn (WaCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'status_label' => WaCampaign::STATUSES[$c->status] ?? $c->status,
                'condominium' => $c->condominium?->name,
                'connection' => $c->connection?->name,
                'total_recipients' => $c->total_recipients,
                'sent_count' => $c->sent_count,
                'failed_count' => $c->failed_count,
                'skipped_count' => $c->skipped_count,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Campaigns/Index', ['campaigns' => $campaigns]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Campaigns/Create', [
            'connections' => WhatsappConnection::where('tenant_id', $tenant->id)->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'connected' => $c->status === 'connected']),
            'condominiums' => Condominium::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'mediaMaxMb' => (int) config('services.evolution.media_max_mb'),
        ]);
    }

    /** Blocos e unidades de um condomínio para a segmentação (consumido via fetch). */
    public function targets(Condominium $condominium): JsonResponse
    {
        abort_unless($condominium->tenant_id === app('tenant')->id, 403);

        $blocks = Block::where('condominium_id', $condominium->id)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($b) => ['value' => $b->id, 'label' => $b->name]);

        $units = Unit::where('condominium_id', $condominium->id)->with('block:id,name')->orderBy('number')->get()
            ->map(fn (Unit $u) => ['value' => $u->id, 'label' => ($u->block?->name ? $u->block->name.' - ' : '').$u->number]);

        return response()->json(['blocks' => $blocks, 'units' => $units]);
    }

    /** Conta os destinatários elegíveis para o alvo informado (prévia no formulário). */
    public function preview(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::exists('condominiums', 'id')->where('tenant_id', $tenant->id)],
            'target_type' => 'required|in:all,blocks,units',
            'block_ids' => 'array',
            'unit_ids' => 'array',
        ]);

        $count = $this->campaigns->previewCount(
            $tenant->id, $data['condominium_id'], $data['target_type'],
            $data['block_ids'] ?? [], $data['unit_ids'] ?? [],
        );

        return response()->json(['count' => $count]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $maxKb = (int) config('services.evolution.media_max_mb') * 1024;

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'connection_id' => ['required', 'uuid', Rule::exists('whatsapp_connections', 'id')->where('tenant_id', $tenant->id)],
            'condominium_id' => ['required', 'uuid', Rule::exists('condominiums', 'id')->where('tenant_id', $tenant->id)],
            'body' => 'required|string|max:4000',
            'target_type' => 'required|in:all,blocks,units',
            'block_ids' => 'array',
            'block_ids.*' => 'uuid',
            'unit_ids' => 'array',
            'unit_ids.*' => 'uuid',
            'throttle_seconds' => 'nullable|integer|min:1|max:300',
            'scheduled_at' => 'nullable|date|after:now',
            'file' => "nullable|file|max:{$maxKb}",
        ]);

        $campaign = WaCampaign::create([
            'tenant_id' => $tenant->id,
            'connection_id' => $data['connection_id'],
            'condominium_id' => $data['condominium_id'],
            'name' => $data['name'],
            'body' => $data['body'],
            'target_type' => $data['target_type'],
            'block_ids' => $data['target_type'] === 'blocks' ? ($data['block_ids'] ?? []) : null,
            'unit_ids' => $data['target_type'] === 'units' ? ($data['unit_ids'] ?? []) : null,
            'throttle_seconds' => $data['throttle_seconds'] ?? 10,
            'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $object = $this->storage->storeRaw(
                tenant: $tenant,
                entityType: 'wa_campaign',
                entityId: $campaign->id,
                contents: file_get_contents($file->getRealPath()),
                filename: $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                visibility: 'tenant',
                condominiumId: $campaign->condominium_id,
            );
            $campaign->update(['media_storage_object_id' => $object->id]);
        }

        $this->campaigns->buildRecipients($campaign);

        return redirect()->route('campaigns.show', $campaign->id)
            ->with('success', 'Campanha criada com '.$campaign->fresh()->total_recipients.' destinatário(s).');
    }

    public function show(WaCampaign $campaign): Response
    {
        $this->authorizeTenant($campaign);

        return Inertia::render('Campaigns/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'body' => $campaign->body,
                'status' => $campaign->status,
                'status_label' => WaCampaign::STATUSES[$campaign->status] ?? $campaign->status,
                'condominium' => $campaign->condominium?->name,
                'connection' => $campaign->connection?->name,
                'connection_connected' => $campaign->connection?->status === 'connected',
                'has_media' => $campaign->media_storage_object_id !== null,
                'throttle_seconds' => $campaign->throttle_seconds,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'skipped_count' => $campaign->skipped_count,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'started_at' => $campaign->started_at?->toIso8601String(),
                'completed_at' => $campaign->completed_at?->toIso8601String(),
                'is_editable' => $campaign->isEditable(),
            ],
            'recipients' => $campaign->recipients()->orderBy('status')->orderBy('name')->limit(500)->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'phone' => $r->phone,
                    'status' => $r->status,
                    'error' => $r->error,
                    'sent_at' => $r->sent_at?->toIso8601String(),
                ]),
        ]);
    }

    public function start(WaCampaign $campaign): RedirectResponse
    {
        $this->authorizeTenant($campaign);

        if (! $campaign->isEditable()) {
            return back()->with('error', 'Esta campanha não pode ser iniciada.');
        }
        if ($campaign->connection?->status !== 'connected') {
            return back()->with('error', 'A conexão da campanha não está conectada.');
        }

        $this->campaigns->start($campaign);

        return back()->with('success', 'Disparo iniciado.');
    }

    public function cancel(WaCampaign $campaign): RedirectResponse
    {
        $this->authorizeTenant($campaign);

        if (in_array($campaign->status, ['sending', 'scheduled'], true)) {
            $campaign->update(['status' => 'cancelled']);
        }

        return back()->with('success', 'Campanha cancelada.');
    }

    public function destroy(WaCampaign $campaign): RedirectResponse
    {
        $this->authorizeTenant($campaign);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campanha removida.');
    }

    // ----- Opt-out (descadastro) -----

    public function optOuts(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Campaigns/OptOuts', [
            'optOuts' => WaOptOut::where('tenant_id', $tenant->id)->orderByDesc('created_at')->limit(500)->get()
                ->map(fn (WaOptOut $o) => [
                    'id' => $o->id,
                    'phone' => $o->phone,
                    'reason' => $o->reason,
                    'created_at' => $o->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function storeOptOut(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate(['phone' => 'required|string|max:30']);

        $phone = WaOptOut::normalizePhone($data['phone']);
        if ($phone) {
            WaOptOut::firstOrCreate(
                ['tenant_id' => $tenant->id, 'phone' => $phone],
                ['reason' => 'Manual', 'created_at' => now()],
            );
        }

        return back()->with('success', 'Telefone adicionado ao descadastro.');
    }

    public function destroyOptOut(WaOptOut $optOut): RedirectResponse
    {
        abort_unless($optOut->tenant_id === app('tenant')->id, 403);
        $optOut->delete();

        return back()->with('success', 'Telefone removido do descadastro.');
    }

    private function authorizeTenant(WaCampaign $campaign): void
    {
        abort_unless($campaign->tenant_id === app('tenant')->id, 403);
    }
}
