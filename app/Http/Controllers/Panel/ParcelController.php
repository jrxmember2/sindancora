<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visão de gestão das encomendas (painel). Acompanhamento e baixa de retirada, escopado por
 * condomínio (gestor tenant-wide vê tudo; papéis escopados veem o seu). Registro fica na portaria.
 */
class ParcelController extends Controller
{
    use ScopesCondominiumsByRole;

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());
        $condominiumIds = $condominiums->pluck('id')->all();
        $selected = $request->input('condominium_id');
        $selected = is_string($selected) && in_array($selected, $condominiumIds, true) ? $selected : null;
        $status = in_array($request->input('status'), array_keys(Parcel::STATUSES), true) ? $request->input('status') : null;

        $parcels = Parcel::with(['condominium:id,name', 'unit:id,number'])
            ->whereIn('condominium_id', $condominiumIds)
            ->when($selected, fn (Builder $q, string $id) => $q->where('condominium_id', $id))
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->orderByRaw("CASE WHEN status = 'awaiting' THEN 0 ELSE 1 END")
            ->orderByDesc('received_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Parcel $p) => [
                'id' => $p->id,
                'description' => $p->description,
                'carrier' => $p->carrier,
                'status' => $p->status,
                'condominium' => $p->condominium?->name,
                'unit' => $p->unit?->number,
                'received_at' => $p->received_at?->toIso8601String(),
                'picked_up_at' => $p->picked_up_at?->toIso8601String(),
            ]);

        return Inertia::render('Parcels/Index', [
            'parcels' => $parcels,
            'condominiums' => $condominiums->map(fn (Condominium $c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'statuses' => Parcel::STATUSES,
            'filters' => ['condominium_id' => $selected, 'status' => $status],
        ]);
    }

    public function markPickedUp(Request $request, Parcel $parcel): RedirectResponse
    {
        $this->authorizeParcel($parcel, $request);
        $parcel->markPickedUp($request->user());

        return back()->with('success', 'Retirada registrada.');
    }

    private function authorizeParcel(Parcel $parcel, Request $request): void
    {
        abort_unless($parcel->tenant_id === app('tenant')->id, 403);
        $allowed = $this->accessibleCondominiums($parcel->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($parcel->condominium_id, $allowed, true), 403);
    }
}
