<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\WaQuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Respostas prontas (canned) usadas pelos atendentes na inbox. Por tenant; uma resposta pode ser
 * marcada como específica de um setor (senão fica disponível em todos). Permissão sectors:manage.
 */
class QuickReplyController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        $replies = WaQuickReply::where('tenant_id', $tenant->id)
            ->with('sector:id,name')
            ->orderBy('sort_order')->orderBy('title')
            ->get()
            ->map(fn (WaQuickReply $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'shortcut' => $r->shortcut,
                'body' => $r->body,
                'sector_id' => $r->sector_id,
                'sector' => $r->sector?->name,
                'sort_order' => $r->sort_order,
            ]);

        return Inertia::render('Settings/QuickReplies', [
            'replies' => $replies,
            'sectors' => Sector::where('tenant_id', $tenant->id)->active()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validateData($request, $tenant->id);

        WaQuickReply::create(array_merge($data, ['tenant_id' => $tenant->id]));

        return back()->with('success', 'Resposta pronta criada.');
    }

    public function update(Request $request, WaQuickReply $quickReply): RedirectResponse
    {
        $this->authorizeTenant($quickReply);
        $data = $this->validateData($request, $quickReply->tenant_id);

        $quickReply->update($data);

        return back()->with('success', 'Resposta pronta atualizada.');
    }

    public function destroy(WaQuickReply $quickReply): RedirectResponse
    {
        $this->authorizeTenant($quickReply);
        $quickReply->delete();

        return back()->with('success', 'Resposta pronta removida.');
    }

    private function validateData(Request $request, string $tenantId): array
    {
        return $request->validate([
            'title' => 'required|string|max:80',
            'shortcut' => 'nullable|string|max:30',
            'body' => 'required|string|max:4000',
            'sort_order' => 'nullable|integer|min:0',
            'sector_id' => ['nullable', 'uuid', Rule::exists('sectors', 'id')->where('tenant_id', $tenantId)],
        ]);
    }

    private function authorizeTenant(WaQuickReply $quickReply): void
    {
        abort_unless($quickReply->tenant_id === app('tenant')->id, 403);
    }
}
