<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\DisciplinaryRecord;
use App\Models\PersonUnitLink;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\DisciplinaryRecordIssued;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisciplinaryRecordController extends Controller
{
    use InteractsWithAttachments, ScopesCondominiumsByRole;

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiumIds = $this->accessibleCondominiums($tenant->id, $request->user())->pluck('id')->all();
        $type = in_array($request->input('type'), array_keys(DisciplinaryRecord::TYPES), true) ? $request->input('type') : null;
        $status = in_array($request->input('status'), array_keys(DisciplinaryRecord::STATUSES), true) ? $request->input('status') : null;

        $records = DisciplinaryRecord::with(['condominium:id,name', 'unit:id,number,block_id', 'unit.block:id,name', 'person:id,name', 'charge:id,status'])
            ->whereIn('condominium_id', $condominiumIds)
            ->when($type, fn (Builder $q, string $value) => $q->where('type', $value))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($request->input('search'), function (Builder $q, string $search) {
                $q->where(function (Builder $nested) use ($search) {
                    $nested->where('title', 'ilike', "%{$search}%")
                        ->orWhere('rule_reference', 'ilike', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN status = 'issued' THEN 0 WHEN status = 'acknowledged' THEN 1 ELSE 2 END")
            ->orderByDesc('issued_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DisciplinaryRecord $record) => $this->payload($record));

        return Inertia::render('Disciplinary/Index', [
            'records' => $records,
            'types' => DisciplinaryRecord::TYPES,
            'statuses' => DisciplinaryRecord::STATUSES,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Disciplinary/Create', [
            'condominiums' => $this->condominiumOptions($request),
            'units' => $this->unitOptions($request),
            'types' => DisciplinaryRecord::TYPES,
            'canGenerateCharge' => $this->canGenerateCharge($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $unit = Unit::with('activeLinks.person')->findOrFail($data['unit_id']);
        abort_unless($unit->condominium_id === $data['condominium_id'], 422, 'Unidade nao pertence ao condominio informado.');
        $this->assertPersonBelongsToUnit($data['person_id'] ?? null, $unit->id);

        $record = DB::transaction(function () use ($data, $request, $unit) {
            $record = DisciplinaryRecord::create([
                'condominium_id' => $data['condominium_id'],
                'unit_id' => $unit->id,
                'person_id' => $data['person_id'] ?? $this->primaryPersonId($unit->id),
                'type' => $data['type'],
                'status' => 'issued',
                'title' => $data['title'],
                'rule_reference' => $data['rule_reference'] ?? null,
                'description' => $data['description'],
                'occurred_on' => $data['occurred_on'] ?? null,
                'amount' => $data['type'] === 'fine' ? ($data['amount'] ?? null) : null,
                'due_date' => $data['type'] === 'fine' ? ($data['due_date'] ?? null) : null,
                'issued_at' => now(),
                'created_by' => $request->user()?->id,
            ]);

            if (($data['generate_charge'] ?? false) && $record->type === 'fine') {
                $charge = $this->createCharge($record, $request);
                $record->update(['charge_id' => $charge->id]);
            }

            return $record;
        });

        try {
            $this->storeAttachments($request, $record, DisciplinaryRecord::ATTACHMENT_ENTITY, 'tenant', $record->condominium_id);
        } catch (StorageQuotaException) {
            // O registro disciplinar nao deve falhar por falta de espaco no anexo.
        }

        $this->notifyResidents($record);

        return redirect()->route('disciplinary.show', $record)->with('success', 'Registro emitido.');
    }

    public function show(Request $request, DisciplinaryRecord $record): Response
    {
        $record = $this->authorizeRecord($record, $request);
        $record->load(['condominium:id,name', 'unit:id,number,block_id', 'unit.block:id,name', 'person:id,name', 'charge:id,status,amount,due_date']);

        return Inertia::render('Disciplinary/Show', [
            'record' => array_merge($this->payload($record), [
                'description' => $record->description,
                'attachments' => $record->attachmentsPayload(),
                'cancellation_reason' => $record->cancellation_reason,
            ]),
            'types' => DisciplinaryRecord::TYPES,
            'statuses' => DisciplinaryRecord::STATUSES,
            'canGenerateCharge' => $this->canGenerateCharge($request) && $record->type === 'fine' && ! $record->charge_id && $record->status !== 'cancelled',
        ]);
    }

    public function generateCharge(Request $request, DisciplinaryRecord $record): RedirectResponse
    {
        $record = $this->authorizeRecord($record, $request);
        abort_unless($this->canGenerateCharge($request), 403);
        abort_unless($record->type === 'fine' && ! $record->charge_id && $record->status !== 'cancelled', 422, 'Esta multa nao pode gerar cobranca.');
        abort_unless((float) $record->amount > 0 && $record->due_date, 422, 'Valor e vencimento sao obrigatorios para gerar cobranca.');

        $charge = $this->createCharge($record, $request);
        $record->update(['charge_id' => $charge->id]);

        return back()->with('success', 'Cobranca criada a partir da multa.');
    }

    public function cancel(Request $request, DisciplinaryRecord $record): RedirectResponse
    {
        $record = $this->authorizeRecord($record, $request);
        $data = $request->validate(['reason' => 'nullable|string|max:180']);

        $record->update([
            'status' => 'cancelled',
            'cancelled_by' => $request->user()?->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        if ($record->charge && $record->charge->status !== 'paid') {
            $record->charge->update(['status' => 'cancelled']);
        }

        return back()->with('success', 'Registro cancelado.');
    }

    private function validateData(Request $request): array
    {
        $condominiumIds = $this->accessibleCondominiums(app('tenant')->id, $request->user())->pluck('id')->all();
        $unitIds = Unit::whereIn('condominium_id', $condominiumIds)->pluck('id')->all();

        $rules = array_merge([
            'condominium_id' => ['required', 'uuid', Rule::in($condominiumIds)],
            'unit_id' => ['required', 'uuid', Rule::in($unitIds)],
            'person_id' => ['nullable', 'uuid'],
            'type' => ['required', Rule::in(array_keys(DisciplinaryRecord::TYPES))],
            'title' => 'required|string|max:180',
            'rule_reference' => 'nullable|string|max:180',
            'description' => 'required|string|max:5000',
            'occurred_on' => 'nullable|date',
            'amount' => 'nullable|required_if:type,fine|numeric|min:0.01|max:99999999',
            'due_date' => 'nullable|required_if:generate_charge,true|date',
            'generate_charge' => 'boolean',
        ], $this->attachmentRules());

        $data = $request->validate($rules);

        if (($data['generate_charge'] ?? false) && ! $this->canGenerateCharge($request)) {
            abort(403, 'Seu plano ou permissao nao permite gerar cobranca.');
        }

        return $data;
    }

    private function createCharge(DisciplinaryRecord $record, Request $request): Charge
    {
        return Charge::create([
            'tenant_id' => $record->tenant_id,
            'condominium_id' => $record->condominium_id,
            'unit_id' => $record->unit_id,
            'person_id' => $record->person_id,
            'type' => 'fine',
            'description' => 'Multa regimental - '.$record->title,
            'reference_month' => now()->format('Y-m'),
            'amount' => $record->amount,
            'due_date' => $record->due_date,
            'fine_rate' => 0,
            'interest_rate' => 0,
            'status' => 'pending',
            'notes' => trim('Origem: multa regimental '.$record->id.'. '.$record->rule_reference),
            'created_by' => $request->user()?->id,
        ]);
    }

    private function authorizeRecord(DisciplinaryRecord $record, Request $request): DisciplinaryRecord
    {
        abort_unless($record->tenant_id === app('tenant')->id, 403);
        $allowed = $this->accessibleCondominiums($record->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($record->condominium_id, $allowed, true), 403);

        return $record;
    }

    private function assertPersonBelongsToUnit(?string $personId, string $unitId): void
    {
        if (! $personId) {
            return;
        }

        abort_unless(
            PersonUnitLink::where('unit_id', $unitId)->where('person_id', $personId)->whereNull('end_date')->exists(),
            422,
            'Pessoa nao possui vinculo ativo com a unidade.',
        );
    }

    private function primaryPersonId(string $unitId): ?string
    {
        return PersonUnitLink::where('unit_id', $unitId)
            ->whereNull('end_date')
            ->orderByDesc('is_primary')
            ->value('person_id');
    }

    private function notifyResidents(DisciplinaryRecord $record): void
    {
        $personIds = PersonUnitLink::where('unit_id', $record->unit_id)
            ->whereNull('end_date')
            ->pluck('person_id');

        if ($personIds->isEmpty()) {
            return;
        }

        $users = User::where('tenant_id', $record->tenant_id)
            ->whereIn('person_id', $personIds)
            ->where('status', 'active')
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new DisciplinaryRecordIssued($record));
        }
    }

    private function canGenerateCharge(Request $request): bool
    {
        return $request->user()?->hasPermission('charges:create')
            && (bool) app('tenant')->activePlan()?->hasModule('financial');
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
            'person' => $record->person?->name,
            'occurred_on' => $record->occurred_on?->toDateString(),
            'issued_at' => $record->issued_at?->toIso8601String(),
            'acknowledged_at' => $record->acknowledged_at?->toIso8601String(),
            'amount' => $record->amount !== null ? (float) $record->amount : null,
            'due_date' => $record->due_date?->toDateString(),
            'charge_id' => $record->charge_id,
            'charge_status' => $record->charge?->status,
        ];
    }

    private function condominiumOptions(Request $request): Collection
    {
        return $this->accessibleCondominiums(app('tenant')->id, $request->user())
            ->map(fn (Condominium $c) => ['value' => $c->id, 'label' => $c->name])
            ->values();
    }

    private function unitOptions(Request $request): Collection
    {
        $condominiumIds = $this->accessibleCondominiums(app('tenant')->id, $request->user())->pluck('id')->all();

        return Unit::with(['condominium:id,name', 'block:id,name', 'activeLinks.person:id,name'])
            ->whereIn('condominium_id', $condominiumIds)
            ->orderBy('number')
            ->get(['id', 'condominium_id', 'block_id', 'number'])
            ->map(fn (Unit $unit) => [
                'value' => $unit->id,
                'condominium_id' => $unit->condominium_id,
                'label' => trim(($unit->condominium?->name ? $unit->condominium->name.' - ' : '').$this->unitLabel($unit)),
                'people' => $unit->activeLinks
                    ->map(fn (PersonUnitLink $link) => $link->person ? ['value' => $link->person->id, 'label' => $link->person->name] : null)
                    ->filter()
                    ->values(),
            ])
            ->values();
    }

    private function unitLabel(?Unit $unit): ?string
    {
        if (! $unit) {
            return null;
        }

        return trim(($unit->block?->name ? $unit->block->name.' - ' : '').$unit->number);
    }
}
