<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChargeController extends Controller
{
    use InteractsWithResident;

    public function index(): Response
    {
        $unitIds = $this->unitIds() ?: ['-'];

        $charges = Charge::whereIn('unit_id', $unitIds)
            ->where('status', '!=', 'cancelled')
            ->with(['condominium:id,name', 'unit:id,number'])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn ($c) => array_merge(
                $c->only(['id', 'description', 'type', 'reference_month', 'amount', 'due_date', 'status']),
                [
                    'current_amount' => $c->currentAmount(),
                    'condominium' => $c->condominium ? ['name' => $c->condominium->name] : null,
                    'unit' => $c->unit ? ['number' => $c->unit->number] : null,
                ],
            ));

        return Inertia::render('Portal/Charges/Index', [
            'open' => $charges->whereIn('status', ['pending', 'overdue'])->values(),
            'paid' => $charges->where('status', 'paid')->values(),
            'types' => Charge::TYPES,
            'statuses' => Charge::STATUSES,
        ]);
    }

    public function show(Charge $charge): Response
    {
        $this->authorizeOwner($charge);
        $charge->load(['condominium:id,name', 'unit:id,number', 'receipt:id']);

        return Inertia::render('Portal/Charges/Show', [
            'charge' => array_merge($charge->toArray(), ['current_amount' => $charge->currentAmount()]),
            'types' => Charge::TYPES,
            'statuses' => Charge::STATUSES,
        ]);
    }

    public function download(Charge $charge): RedirectResponse|StreamedResponse
    {
        $this->authorizeOwner($charge);
        $object = $charge->receipt;
        abort_unless($object, 404);

        $disk = Storage::disk($object->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($object->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }

    /** A cobrança precisa ser do tenant e de uma unidade do morador. */
    private function authorizeOwner(Charge $charge): void
    {
        abort_unless($charge->tenant_id === app('tenant')->id, 403);
        abort_unless(in_array($charge->unit_id, $this->unitIds(), true), 403);
    }
}
