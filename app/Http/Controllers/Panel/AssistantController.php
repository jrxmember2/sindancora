<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\AiException;
use App\Services\AI\AssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $conversations = AiConversation::where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'updated_at']);

        $current = null;
        $messages = [];
        if ($request->conversation) {
            $current = AiConversation::where('tenant_id', $tenant->id)->find($request->conversation);
            if ($current) {
                $messages = $current->messages()->get(['role', 'content', 'created_at']);
            }
        }

        return Inertia::render('IA/Assistant', [
            'configured' => $this->assistant->configured(),
            'conversations' => $conversations,
            'conversation' => $current ? ['id' => $current->id, 'title' => $current->title] : null,
            'messages' => $messages,
            'draft' => $request->session()->get('draft'),
        ]);
    }

    public function message(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = $this->resolveConversation($data['conversation_id'] ?? null, $data['message']);

        try {
            $this->assistant->chat($conversation, app('tenant'), $data['message']);
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        return $this->redirect($conversation);
    }

    public function delinquency(Request $request): RedirectResponse
    {
        $data = $request->validate(['conversation_id' => 'nullable|uuid']);
        $conversation = $this->resolveConversation($data['conversation_id'] ?? null, 'Análise de inadimplência');

        try {
            $answer = $this->assistant->analyzeDelinquency(app('tenant'));
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        $conversation->messages()->create(['role' => 'user', 'content' => 'Faça uma análise da inadimplência com plano de ação.']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => $answer]);
        $conversation->touch();

        return $this->redirect($conversation);
    }

    public function announcement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|uuid',
            'prompt' => 'required|string|max:1000',
        ]);
        $conversation = $this->resolveConversation($data['conversation_id'] ?? null, 'Rascunho: '.$data['prompt']);

        try {
            $draft = $this->assistant->draftAnnouncement(app('tenant'), $data['prompt']);
        } catch (AiException $e) {
            return $this->redirect($conversation)->with('error', $e->getMessage());
        }

        $conversation->messages()->create(['role' => 'user', 'content' => 'Rascunho de comunicado: '.$data['prompt']]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => "**{$draft['title']}**\n\n{$draft['body']}"]);
        $conversation->touch();

        // Disponibiliza o rascunho estruturado para o botão "Usar em Comunicados".
        return $this->redirect($conversation)->with('draft', $draft);
    }

    public function destroy(AiConversation $conversation): RedirectResponse
    {
        abort_unless($conversation->tenant_id === app('tenant')->id, 403);
        $conversation->delete();

        return redirect()->route('assistant.index')->with('success', 'Conversa removida.');
    }

    private function resolveConversation(?string $id, string $titleSeed): AiConversation
    {
        $tenant = app('tenant');

        if ($id) {
            $existing = AiConversation::where('tenant_id', $tenant->id)->find($id);
            if ($existing) {
                return $existing;
            }
        }

        return AiConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'title' => Str::limit($titleSeed, 60),
        ]);
    }

    private function redirect(AiConversation $conversation): RedirectResponse
    {
        return redirect()->route('assistant.index', ['conversation' => $conversation->id]);
    }
}
