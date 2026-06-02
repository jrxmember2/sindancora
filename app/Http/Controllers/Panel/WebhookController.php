<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $webhooks = Webhook::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Webhook $w) => [
                'id' => $w->id,
                'url' => $w->url,
                'description' => $w->description,
                'events' => $w->events,
                'secret' => $w->secret,
                'active' => $w->active,
                'created_at' => $w->created_at?->toIso8601String(),
            ]);

        $deliveries = WebhookDelivery::whereIn('webhook_id', $webhooks->pluck('id'))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['event', 'response_status', 'duration_ms', 'attempts', 'delivered_at', 'failed_at', 'created_at']);

        return Inertia::render('Settings/Webhooks', [
            'webhooks' => $webhooks,
            'events' => collect(Webhook::EVENTS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'deliveries' => $deliveries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validateData($request);

        Webhook::create([
            'tenant_id' => $tenant->id,
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'events' => $data['events'],
            'active' => $data['active'] ?? true,
            'secret' => Str::random(48),
        ]);

        return back()->with('success', 'Webhook criado.');
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $webhook = $this->authorizeTenant($webhook);
        $data = $this->validateData($request);

        $webhook->update([
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'events' => $data['events'],
            'active' => $data['active'] ?? true,
        ]);

        return back()->with('success', 'Webhook atualizado.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook = $this->authorizeTenant($webhook);
        $webhook->delete();

        return back()->with('success', 'Webhook removido.');
    }

    /** Envia um evento de teste (ping) para a URL do webhook. */
    public function test(Webhook $webhook): RedirectResponse
    {
        $webhook = $this->authorizeTenant($webhook);

        DeliverWebhook::dispatch($webhook->id, 'ping', [
            'event' => 'ping',
            'created_at' => now()->toIso8601String(),
            'data' => ['message' => 'Teste de webhook do SindÂncora.'],
        ]);

        return back()->with('success', 'Evento de teste enfileirado. Veja o resultado em "últimas entregas".');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'url' => 'required|url|max:500',
            'description' => 'nullable|string|max:200',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:'.implode(',', array_keys(Webhook::EVENTS)),
            'active' => 'boolean',
        ]);
    }

    private function authorizeTenant(Webhook $webhook): Webhook
    {
        abort_unless($webhook->tenant_id === app('tenant')->id, 403);

        return $webhook;
    }
}
