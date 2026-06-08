<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Condominium;
use App\Models\Tenant;
use App\Services\AI\AiException;
use App\Services\AI\AssistantService;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    private const AI_RESOURCE = 'ai_interactions_monthly';

    public function __construct(
        private readonly AssistantService $assistant,
        private readonly PlanLimitService $planLimits,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant);

        $conversations = $this->conversationQuery($tenant, $condominiums)
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'condominium_id', 'updated_at'])
            ->map(fn (AiConversation $conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'condominium_id' => $conversation->condominium_id,
                'condominium' => $conversation->condominium ? [
                    'id' => $conversation->condominium->id,
                    'name' => $conversation->condominium->name,
                ] : null,
                'updated_at' => $conversation->updated_at,
            ]);

        $current = $this->findAccessibleConversation($request->conversation, $tenant, $condominiums);
        $selectedCondominium = $this->selectedCondominium($request, $condominiums, $current);
        $messages = [];
        if ($current) {
            $messages = $current->messages()->get(['role', 'content', 'sources', 'created_at']);
        }

        return Inertia::render('IA/Assistant', [
            'configured' => $this->assistant->configured(),
            'conversations' => $conversations,
            'conversation' => $current ? [
                'id' => $current->id,
                'title' => $current->title,
                'condominium_id' => $current->condominium_id,
                'condominium' => $current->condominium ? [
                    'id' => $current->condominium->id,
                    'name' => $current->condominium->name,
                ] : null,
            ] : null,
            'messages' => $messages,
            'condominiums' => $condominiums->map(fn (Condominium $condominium) => [
                'value' => $condominium->id,
                'label' => $condominium->name,
            ])->values(),
            'selectedCondominiumId' => $selectedCondominium?->id,
            'requiresCondominium' => $condominiums->count() !== 1 && ! $selectedCondominium,
            'draft' => $request->session()->get('draft'),
            'aiUsage' => $this->planLimits->getResourceUsage($tenant, self::AI_RESOURCE),
        ]);
    }

    public function message(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'condominium_id' => 'nullable|uuid',
            'message' => 'required|string|max:4000',
        ]);

        $tenant = app('tenant');
        if ($limited = $this->blockedByLimit($tenant)) {
            return $limited;
        }

        $condominiums = $this->accessibleCondominiums($tenant);
        $conversation = $this->findAccessibleConversation($data['conversation_id'] ?? null, $tenant, $condominiums);
        if (($data['conversation_id'] ?? null) && ! $conversation) {
            return back()->with('error', 'Conversa nao encontrada ou fora do seu escopo.');
        }

        $condominium = $this->selectedCondominium($request, $condominiums, $conversation);
        if ($error = $this->condominiumSelectionError($request, $condominiums, $condominium, $conversation)) {
            return $error;
        }

        $conversation = $this->resolveConversation($conversation, $data['message'], $condominium);

        try {
            $this->assistant->chat($conversation, $tenant, $data['message'], $condominium);
            $this->planLimits->increment($tenant, self::AI_RESOURCE);
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        return $this->redirect($conversation);
    }

    public function delinquency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'condominium_id' => 'nullable|uuid',
        ]);

        $tenant = app('tenant');
        if ($limited = $this->blockedByLimit($tenant)) {
            return $limited;
        }

        $condominiums = $this->accessibleCondominiums($tenant);
        $conversation = $this->findAccessibleConversation($data['conversation_id'] ?? null, $tenant, $condominiums);
        if (($data['conversation_id'] ?? null) && ! $conversation) {
            return back()->with('error', 'Conversa nao encontrada ou fora do seu escopo.');
        }

        $condominium = $this->selectedCondominium($request, $condominiums, $conversation);
        if ($error = $this->condominiumSelectionError($request, $condominiums, $condominium, $conversation)) {
            return $error;
        }

        $conversation = $this->resolveConversation($conversation, 'Analise de inadimplencia', $condominium);

        try {
            $answer = $this->assistant->analyzeDelinquency($tenant, $condominium);
            $this->planLimits->increment($tenant, self::AI_RESOURCE);
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        $conversation->messages()->create(['role' => 'user', 'content' => 'Faca uma analise da inadimplencia com plano de acao.']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => $answer]);
        $conversation->touch();

        return $this->redirect($conversation);
    }

    public function announcement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'condominium_id' => 'nullable|uuid',
            'prompt' => 'required|string|max:1000',
        ]);

        $tenant = app('tenant');
        if ($limited = $this->blockedByLimit($tenant)) {
            return $limited;
        }

        $condominiums = $this->accessibleCondominiums($tenant);
        $conversation = $this->findAccessibleConversation($data['conversation_id'] ?? null, $tenant, $condominiums);
        if (($data['conversation_id'] ?? null) && ! $conversation) {
            return back()->with('error', 'Conversa nao encontrada ou fora do seu escopo.');
        }

        $condominium = $this->selectedCondominium($request, $condominiums, $conversation);
        if ($error = $this->condominiumSelectionError($request, $condominiums, $condominium, $conversation)) {
            return $error;
        }

        $conversation = $this->resolveConversation($conversation, 'Rascunho: '.$data['prompt'], $condominium);

        try {
            $draft = $this->assistant->draftAnnouncement($tenant, $data['prompt'], $condominium);
            $this->planLimits->increment($tenant, self::AI_RESOURCE);
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        $conversation->messages()->create(['role' => 'user', 'content' => 'Rascunho de comunicado: '.$data['prompt']]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => "**{$draft['title']}**\n\n{$draft['body']}"]);
        $conversation->touch();

        return $this->redirect($conversation)->with('draft', $draft);
    }

    public function destroy(AiConversation $conversation): RedirectResponse
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant);

        abort_unless($conversation->tenant_id === $tenant->id && $this->canAccessConversation($conversation, $condominiums), 403);
        $conversation->delete();

        return redirect()->route('assistant.index')->with('success', 'Conversa removida.');
    }

    private function accessibleCondominiums(Tenant $tenant): Collection
    {
        $user = Auth::user();
        $query = Condominium::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name');

        if (! $user?->isSuperAdmin() && ! $this->hasTenantWideCondominiumAccess()) {
            $ids = $user?->userRoles()
                ->whereNotNull('condominium_id')
                ->pluck('condominium_id')
                ->unique()
                ->values()
                ->all() ?? [];

            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'name']);
    }

    private function conversationQuery(Tenant $tenant, Collection $condominiums)
    {
        $query = AiConversation::where('tenant_id', $tenant->id)
            ->with('condominium:id,name');

        if (! $this->hasTenantWideCondominiumAccess()) {
            $ids = $condominiums->pluck('id')->all();
            $query->where(function ($q) use ($ids) {
                $q->whereIn('condominium_id', $ids)
                    ->orWhere(function ($old) {
                        $old->whereNull('condominium_id')
                            ->where('user_id', Auth::id());
                    });
            });
        }

        return $query;
    }

    private function findAccessibleConversation(?string $id, Tenant $tenant, Collection $condominiums): ?AiConversation
    {
        if (! $id) {
            return null;
        }

        $conversation = AiConversation::where('tenant_id', $tenant->id)
            ->with('condominium:id,name')
            ->find($id);

        if (! $conversation || ! $this->canAccessConversation($conversation, $condominiums)) {
            return null;
        }

        return $conversation;
    }

    private function canAccessConversation(AiConversation $conversation, Collection $condominiums): bool
    {
        if ($conversation->condominium_id === null) {
            return $this->hasTenantWideCondominiumAccess() || $conversation->user_id === Auth::id();
        }

        return $condominiums->contains('id', $conversation->condominium_id);
    }

    private function selectedCondominium(Request $request, Collection $condominiums, ?AiConversation $conversation = null): ?Condominium
    {
        if ($conversation?->condominium_id) {
            return $condominiums->firstWhere('id', $conversation->condominium_id);
        }

        if ($request->filled('condominium_id')) {
            return $condominiums->firstWhere('id', $request->input('condominium_id'));
        }

        if ($condominiums->count() === 1) {
            return $condominiums->first();
        }

        return null;
    }

    private function condominiumSelectionError(Request $request, Collection $condominiums, ?Condominium $condominium, ?AiConversation $conversation = null): ?RedirectResponse
    {
        if ($condominiums->isEmpty()) {
            return back()->with('error', 'Nenhum condominio ativo esta disponivel para o assistente.');
        }

        if ($request->filled('condominium_id') && ! $condominium && ! $conversation?->condominium_id) {
            return back()->with('error', 'Condominio selecionado nao esta disponivel para o seu usuario.');
        }

        if (! $condominium && $condominiums->count() > 1) {
            return back()->with('error', 'Selecione um condominio antes de usar o assistente.');
        }

        return null;
    }

    private function resolveConversation(?AiConversation $conversation, string $titleSeed, ?Condominium $condominium): AiConversation
    {
        if ($conversation) {
            if (! $conversation->condominium_id && $condominium) {
                $conversation->forceFill(['condominium_id' => $condominium->id])->save();
            }

            return $conversation;
        }

        $tenant = app('tenant');

        return AiConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'condominium_id' => $condominium?->id,
            'title' => Str::limit($titleSeed, 60),
        ]);
    }

    private function hasTenantWideCondominiumAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->isSuperAdmin() || $user?->userRoles()->whereNull('condominium_id')->exists());
    }

    private function redirect(AiConversation $conversation): RedirectResponse
    {
        return redirect()->route('assistant.index', ['conversation' => $conversation->id]);
    }

    private function blockedByLimit(Tenant $tenant): ?RedirectResponse
    {
        try {
            $this->planLimits->check($tenant, self::AI_RESOURCE);
        } catch (PlanLimitException $e) {
            return back()->with('error', "Limite mensal de interacoes com IA atingido ({$e->current}/{$e->limit}). Ajuste o limite no plano ou no perfil do tenant.");
        }

        return null;
    }
}
