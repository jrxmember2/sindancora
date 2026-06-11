<?php

namespace App\Http\Controllers\Portaria;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Parcel;
use App\Models\PersonUnitLink;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ParcelArrived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Encomendas na área da portaria (porteiro). Registra a chegada (notifica o morador da unidade)
 * e dá baixa na retirada. Escopo por tenant (todos os condomínios), como o resto da portaria.
 */
class ParcelController extends Controller
{
    use InteractsWithAttachments;

    public function index(): Response
    {
        $parcels = Parcel::with(['condominium:id,name', 'unit:id,number'])
            ->orderByRaw("CASE WHEN status = 'awaiting' THEN 0 ELSE 1 END")
            ->orderByDesc('received_at')
            ->limit(100)
            ->get()
            ->map(fn (Parcel $p) => $this->payload($p));

        return Inertia::render('Portaria/Parcels', [
            'parcels' => $parcels,
            'condominiums' => $this->condominiumOptions(),
            'statuses' => Parcel::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condominium_id' => 'required|uuid',
            'unit_id' => 'required|uuid',
            'description' => 'required|string|max:255',
            'carrier' => 'nullable|string|max:150',
            'tracking_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Garante que a unidade pertence ao condomínio informado (e ao tenant, via global scope).
        $unit = Unit::where('id', $data['unit_id'])->where('condominium_id', $data['condominium_id'])->first();
        abort_unless($unit !== null, 422);

        $parcel = Parcel::create([
            'condominium_id' => $data['condominium_id'],
            'unit_id' => $data['unit_id'],
            'description' => $data['description'],
            'carrier' => $data['carrier'] ?? null,
            'tracking_code' => $data['tracking_code'] ?? null,
            'status' => 'awaiting',
            'received_by' => $request->user()?->id,
            'received_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        // Foto opcional; estouro de cota não derruba o registro.
        try {
            $this->storeAttachments($request, $parcel, Parcel::ATTACHMENT_ENTITY, 'tenant', $parcel->condominium_id, 'photo');
        } catch (StorageQuotaException) {
            // ignora
        }

        $this->notifyResidents($parcel);

        return back()->with('success', 'Encomenda registrada e morador avisado.');
    }

    public function markPickedUp(Request $request, Parcel $parcel): RedirectResponse
    {
        abort_unless($parcel->tenant_id === app('tenant')->id, 403);
        $parcel->markPickedUp($request->user());

        return back()->with('success', 'Retirada registrada.');
    }

    /** Notifica os moradores (usuários) vinculados à unidade da encomenda. */
    private function notifyResidents(Parcel $parcel): void
    {
        $personIds = PersonUnitLink::where('unit_id', $parcel->unit_id)
            ->whereNull('end_date')
            ->pluck('person_id');

        if ($personIds->isEmpty()) {
            return;
        }

        $users = User::where('tenant_id', $parcel->tenant_id)
            ->whereIn('person_id', $personIds)
            ->where('status', 'active')
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new ParcelArrived($parcel));
        }
    }

    private function payload(Parcel $parcel): array
    {
        return [
            'id' => $parcel->id,
            'description' => $parcel->description,
            'carrier' => $parcel->carrier,
            'tracking_code' => $parcel->tracking_code,
            'status' => $parcel->status,
            'condominium' => $parcel->condominium?->name,
            'unit' => $parcel->unit?->number,
            'received_at' => $parcel->received_at?->toIso8601String(),
            'picked_up_at' => $parcel->picked_up_at?->toIso8601String(),
            'photo' => $parcel->attachmentsPayload()[0]['id'] ?? null,
        ];
    }

    /** Condomínios do tenant com unidades, para o formulário. */
    private function condominiumOptions(): array
    {
        return Condominium::orderBy('name')
            ->with(['units:id,number,condominium_id'])
            ->get()
            ->map(fn (Condominium $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'units' => $c->units->map(fn (Unit $u) => ['id' => $u->id, 'number' => $u->number]),
            ])
            ->all();
    }
}
