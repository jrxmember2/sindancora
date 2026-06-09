<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\CondominiumPublicLink;
use App\Models\PublicSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão dos links públicos/QR por condomínio: gerar/rotacionar token, habilitar ações
 * (auto-cadastro e ocorrência) e ativar/desativar o link. Respeita o escopo de condomínios
 * por papel do usuário.
 */
class PublicLinkController extends Controller
{
    use ScopesCondominiumsByRole;

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());

        $links = CondominiumPublicLink::whereIn('condominium_id', $condominiums->pluck('id'))
            ->get()
            ->keyBy('condominium_id');

        $pendingByCondominium = PublicSubmission::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->whereIn('condominium_id', $condominiums->pluck('id'))
            ->selectRaw('condominium_id, count(*) as total')
            ->groupBy('condominium_id')
            ->pluck('total', 'condominium_id');

        $rows = $condominiums->map(function (Condominium $condo) use ($links, $pendingByCondominium) {
            $link = $links->get($condo->id);

            return [
                'condominium_id' => $condo->id,
                'condominium_name' => $condo->name,
                'has_link' => (bool) $link,
                'token' => $link?->token,
                'url' => $link ? $link->publicUrl() : null,
                'active' => $link?->active ?? false,
                'allow_resident_signup' => $link?->allow_resident_signup ?? true,
                'allow_occurrence' => $link?->allow_occurrence ?? true,
                'pending' => (int) ($pendingByCondominium[$condo->id] ?? 0),
            ];
        })->values();

        return Inertia::render('PublicLinks/Index', [
            'links' => $rows,
            'canManage' => Auth::user()->hasPermission('public_links:manage'),
            'pendingTotal' => (int) $pendingByCondominium->sum(),
        ]);
    }

    /** Cria o link (se não existir) ou rotaciona o token, invalidando o anterior. */
    public function generate(Request $request, Condominium $condominium): RedirectResponse
    {
        $this->authorizeCondominium($condominium, $request);

        $link = CondominiumPublicLink::firstOrNew(['condominium_id' => $condominium->id]);
        $rotated = $link->exists;
        $link->tenant_id = $condominium->tenant_id;
        $link->token = CondominiumPublicLink::generateToken();
        $link->active = true;
        $link->save();

        return back()->with('success', $rotated
            ? 'Novo link gerado. O link anterior deixou de funcionar.'
            : 'Link público gerado.');
    }

    /** Atualiza as ações habilitadas e o status ativo/inativo do link. */
    public function update(Request $request, Condominium $condominium): RedirectResponse
    {
        $this->authorizeCondominium($condominium, $request);

        $data = $request->validate([
            'active' => 'boolean',
            'allow_resident_signup' => 'boolean',
            'allow_occurrence' => 'boolean',
        ]);

        $link = CondominiumPublicLink::firstOrNew(['condominium_id' => $condominium->id]);
        if (! $link->exists) {
            $link->tenant_id = $condominium->tenant_id;
            $link->token = CondominiumPublicLink::generateToken();
        }
        $link->fill($data);
        $link->save();

        return back()->with('success', 'Link público atualizado.');
    }

    private function authorizeCondominium(Condominium $condominium, Request $request): void
    {
        abort_unless($condominium->tenant_id === app('tenant')->id, 403);

        $allowedIds = $this->accessibleCondominiums($condominium->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($condominium->id, $allowedIds, true), 403);
    }
}
