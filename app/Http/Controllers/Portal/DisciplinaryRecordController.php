<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\DisciplinaryRecord;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisciplinaryRecordController extends Controller
{
    use InteractsWithResident;

    public function index(): Response
    {
        $records = DisciplinaryRecord::with(['condominium:id,name', 'unit:id,number,block_id', 'unit.block:id,name', 'charge:id,status'])
            ->visibleToResident($this->unitIds())
            ->orderByDesc('issued_at')
            ->limit(100)
            ->get()
            ->map(fn (DisciplinaryRecord $record) => $this->payload($record));

        return Inertia::render('Portal/Disciplinary/Index', [
            'records' => $records,
            'types' => DisciplinaryRecord::TYPES,
            'statuses' => DisciplinaryRecord::STATUSES,
        ]);
    }

    public function show(DisciplinaryRecord $record): Response
    {
        $this->authorizeRecord($record);
        $record->load(['condominium:id,name', 'unit:id,number,block_id', 'unit.block:id,name', 'person:id,name', 'charge:id,status,amount,due_date']);

        return Inertia::render('Portal/Disciplinary/Show', [
            'record' => array_merge($this->payload($record), [
                'description' => $record->description,
                'person' => $record->person?->name,
                'attachments' => $record->attachmentsPayload(),
            ]),
            'types' => DisciplinaryRecord::TYPES,
            'statuses' => DisciplinaryRecord::STATUSES,
        ]);
    }

    public function acknowledge(DisciplinaryRecord $record): RedirectResponse
    {
        $this->authorizeRecord($record);

        if ($record->status === 'issued') {
            $record->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $this->resident()->id,
            ]);
        }

        return back()->with('success', 'Ciencia registrada.');
    }

    private function authorizeRecord(DisciplinaryRecord $record): void
    {
        abort_unless(in_array($record->unit_id, $this->unitIds(), true), 403);
        abort_unless($record->status !== 'cancelled', 404);
    }

    private function payload(DisciplinaryRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'status' => $record->status,
            'title' => $record->title,
            'rule_reference' => $record->rule_reference,
            'condominium' => $record->condominium?->name,
            'unit' => $this->unitLabel($record->unit),
            'occurred_on' => $record->occurred_on?->toDateString(),
            'issued_at' => $record->issued_at?->toIso8601String(),
            'acknowledged_at' => $record->acknowledged_at?->toIso8601String(),
            'amount' => $record->amount !== null ? (float) $record->amount : null,
            'due_date' => $record->due_date?->toDateString(),
            'charge_id' => $record->charge_id,
            'charge_status' => $record->charge?->status,
        ];
    }

    private function unitLabel(?Unit $unit): ?string
    {
        if (! $unit) {
            return null;
        }

        return trim(($unit->block?->name ? $unit->block->name.' - ' : '').$unit->number);
    }
}
