<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Parcel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Encomendas do morador (portal): lista as encomendas das suas unidades e permite confirmar a retirada.
 */
class ParcelController extends Controller
{
    use InteractsWithResident;

    public function index(): Response
    {
        $parcels = Parcel::with(['condominium:id,name', 'unit:id,number'])
            ->whereIn('unit_id', $this->unitIds())
            ->orderByRaw("CASE WHEN status = 'awaiting' THEN 0 ELSE 1 END")
            ->orderByDesc('received_at')
            ->limit(100)
            ->get()
            ->map(fn (Parcel $p) => [
                'id' => $p->id,
                'description' => $p->description,
                'carrier' => $p->carrier,
                'status' => $p->status,
                'condominium' => $p->condominium?->name,
                'unit' => $p->unit?->number,
                'received_at' => $p->received_at?->toIso8601String(),
                'picked_up_at' => $p->picked_up_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Parcels/Index', [
            'parcels' => $parcels,
            'statuses' => Parcel::STATUSES,
        ]);
    }

    public function confirmPickup(Parcel $parcel): RedirectResponse
    {
        abort_unless(in_array($parcel->unit_id, $this->unitIds(), true), 403);
        $parcel->markPickedUp(\Illuminate\Support\Facades\Auth::user());

        return back()->with('success', 'Retirada confirmada.');
    }
}
