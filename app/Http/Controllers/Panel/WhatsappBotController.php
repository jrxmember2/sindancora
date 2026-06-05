<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\WhatsappBotSetting;
use App\Models\WhatsappConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuração do chatbot de triagem: liga/desliga o bot por conexão e edita o cabeçalho do menu
 * de condomínio (quando a conexão atende >1); e as mensagens por condomínio (saudação, cabeçalho
 * do menu de setores e texto de opção inválida). Permissão sectors:manage.
 */
class WhatsappBotController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $connections = WhatsappConnection::where('tenant_id', $tenant->id)
            ->with('condominiums:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsappConnection $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'bot_enabled' => $c->bot_enabled,
                'condominium_menu_header' => $c->condominium_menu_header,
                'serves_multiple' => $c->condominiums->count() > 1,
                'condominiums' => $c->condominiums->pluck('name'),
            ]);

        $settings = Condominium::where('tenant_id', $tenant->id)
            ->with('botSetting')
            ->orderBy('name')
            ->get()
            ->map(fn (Condominium $c) => [
                'condominium_id' => $c->id,
                'condominium' => $c->name,
                'is_enabled' => $c->botSetting?->is_enabled ?? true,
                'greeting_message' => $c->botSetting?->greeting_message,
                'sector_menu_header' => $c->botSetting?->sector_menu_header,
                'invalid_option_message' => $c->botSetting?->invalid_option_message,
            ]);

        return Inertia::render('Settings/Chatbot', [
            'connections' => $connections,
            'settings' => $settings,
            'defaults' => [
                'greeting' => WhatsappBotSetting::DEFAULT_GREETING,
                'sector_menu_header' => WhatsappBotSetting::DEFAULT_SECTOR_MENU_HEADER,
                'invalid' => WhatsappBotSetting::DEFAULT_INVALID,
            ],
        ]);
    }

    public function updateConnection(Request $request, WhatsappConnection $connection): RedirectResponse
    {
        abort_unless($connection->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'bot_enabled' => 'boolean',
            'condominium_menu_header' => 'nullable|string|max:1000',
        ]);

        $connection->update([
            'bot_enabled' => $request->boolean('bot_enabled'),
            'condominium_menu_header' => $data['condominium_menu_header'] ?? null,
        ]);

        return back()->with('success', 'Chatbot da conexão atualizado.');
    }

    public function updateCondominium(Request $request, Condominium $condominium): RedirectResponse
    {
        abort_unless($condominium->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'is_enabled' => 'boolean',
            'greeting_message' => 'nullable|string|max:1000',
            'sector_menu_header' => 'nullable|string|max:1000',
            'invalid_option_message' => 'nullable|string|max:1000',
        ]);

        WhatsappBotSetting::updateOrCreate(
            ['condominium_id' => $condominium->id],
            [
                'tenant_id' => $condominium->tenant_id,
                'is_enabled' => $request->boolean('is_enabled'),
                'greeting_message' => $data['greeting_message'] ?? null,
                'sector_menu_header' => $data['sector_menu_header'] ?? null,
                'invalid_option_message' => $data['invalid_option_message'] ?? null,
            ],
        );

        return back()->with('success', 'Mensagens do chatbot atualizadas.');
    }
}
