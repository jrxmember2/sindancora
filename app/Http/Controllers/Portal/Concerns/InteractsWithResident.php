<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Person;
use App\Models\PersonUnitLink;
use Illuminate\Support\Facades\Auth;

/**
 * Helpers de escopo do morador logado: a pessoa vinculada, suas unidades ativas
 * e os condomínios correspondentes. Garante que o portal só enxergue os próprios dados.
 */
trait InteractsWithResident
{
    private ?Person $resident = null;

    protected function resident(): Person
    {
        if ($this->resident === null) {
            $person = Auth::user()->person;
            abort_unless($person !== null, 403, 'Conta sem vínculo de morador.');
            $this->resident = $person;
        }

        return $this->resident;
    }

    /** Vínculos ativos (sem data de fim) do morador, com unidade e condomínio. */
    protected function activeLinks()
    {
        return PersonUnitLink::where('person_id', $this->resident()->id)
            ->whereNull('end_date')
            ->with(['unit:id,number,block_id,condominium_id', 'unit.block:id,name', 'unit.condominium:id,name'])
            ->get();
    }

    /** IDs das unidades ativas do morador. */
    protected function unitIds(): array
    {
        return PersonUnitLink::where('person_id', $this->resident()->id)
            ->whereNull('end_date')
            ->pluck('unit_id')
            ->all();
    }

    /** IDs dos condomínios onde o morador tem vínculo ativo. */
    protected function condominiumIds(): array
    {
        return PersonUnitLink::where('person_id', $this->resident()->id)
            ->whereNull('end_date')
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->distinct()
            ->pluck('units.condominium_id')
            ->all();
    }
}
