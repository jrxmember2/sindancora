<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\PersonUnitLink;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    use InteractsWithResident;

    public function show(): Response
    {
        $person = $this->resident();

        $links = PersonUnitLink::where('person_id', $person->id)
            ->with(['unit:id,number,type,area_m2,block_id,condominium_id', 'unit.block:id,name', 'unit.condominium:id,name'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'type' => $l->type,
                'is_primary' => $l->is_primary,
                'start_date' => $l->start_date,
                'end_date' => $l->end_date,
                'unit' => [
                    'number' => $l->unit?->number,
                    'type' => $l->unit?->type,
                    'area' => $l->unit?->area_m2,
                    'block' => $l->unit?->block?->name,
                    'condominium' => $l->unit?->condominium?->name,
                ],
            ]);

        return Inertia::render('Portal/Unit/Show', [
            'person' => [
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
            ],
            'links' => $links,
            'linkTypes' => PersonUnitLink::typeLabels(),
        ]);
    }
}
