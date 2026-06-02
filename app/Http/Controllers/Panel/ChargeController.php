<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\PersonUnitLink;
use App\Models\Unit;
use App\Services\ChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChargeController extends Controller
{
    public function __construct(private readonly ChargeService $service) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $charges = Charge::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'unit:id,number', 'person:id,name'])
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->reference_month, fn ($q, $m) => $q->where('reference_month', $m))
            ->when($request->search, fn ($q, $s) => $q->where('description', 'ilike', "%{$s}%"))
            ->orderByDesc('due_date')
            ->paginate(20)
            ->withQueryString();

        $base = Charge::where('tenant_id', $tenant->id)
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id));

        $kpis = [
            'open' => (clone $base)->whereIn('status', ['pending', 'overdue'])->sum('amount'),
            'overdue' => (clone $base)->overdue()->sum('amount'),
            'received_month' => (clone $base)->where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('paid_amount'),
        ];

        return Inertia::render('Charges/Index', [
            'charges' => $charges,
            'kpis' => $kpis,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'types' => Charge::TYPES,
            'statuses' => Charge::STATUSES,
            'filters' => $request->only(['condominium_id', 'status', 'reference_month', 'search']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Charges/Create', $this->formData($tenant->id));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);

        $charge = Charge::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]));

        return redirect()->route('charges.show', $charge)->with('success', 'Cobrança criada.');
    }

    public function show(Charge $charge): Response
    {
        $charge = $this->authorizeTenant($charge);
        $charge->load(['condominium:id,name', 'unit:id,number', 'person:id,name', 'creator:id,name', 'receipt']);

        return Inertia::render('Charges/Show', [
            'charge' => array_merge($charge->toArray(), ['current_amount' => $charge->currentAmount()]),
            'types' => Charge::TYPES,
            'statuses' => Charge::STATUSES,
            'canMarkPaid' => Auth::user()->hasPermission('charges:mark_paid'),
            'canUpdate' => Auth::user()->hasPermission('charges:update'),
        ]);
    }

    public function edit(Charge $charge): Response
    {
        $charge = $this->authorizeTenant($charge);
        $tenant = app('tenant');

        return Inertia::render('Charges/Edit', array_merge(
            ['charge' => $charge],
            $this->formData($tenant->id),
        ));
    }

    public function update(Request $request, Charge $charge): RedirectResponse
    {
        $charge = $this->authorizeTenant($charge);
        abort_if($charge->status === 'paid', 422, 'Cobrança paga não pode ser editada.');

        $data = $this->validated($request, $charge->tenant_id);
        $charge->update($data);

        return redirect()->route('charges.show', $charge)->with('success', 'Cobrança atualizada.');
    }

    public function destroy(Charge $charge): RedirectResponse
    {
        $charge = $this->authorizeTenant($charge);

        // Cobrança paga é cancelada (mantém histórico); demais podem ser removidas.
        if ($charge->status === 'paid') {
            abort(422, 'Cobrança paga não pode ser removida.');
        }

        $charge->update(['status' => 'cancelled']);
        $charge->delete();

        return redirect()->route('charges.index')->with('success', 'Cobrança cancelada.');
    }

    public function registerPayment(Request $request, Charge $charge): RedirectResponse
    {
        $charge = $this->authorizeTenant($charge);
        abort_if($charge->status === 'paid', 422, 'Cobrança já está paga.');

        $data = $request->validate([
            'paid_at' => 'required|date|before_or_equal:today',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
        ]);

        $this->service->registerPayment($charge, $data, $request->file('receipt'));

        return back()->with('success', 'Pagamento registrado.');
    }

    public function download(Charge $charge): RedirectResponse|StreamedResponse
    {
        $charge = $this->authorizeTenant($charge);
        $object = $charge->receipt;
        abort_unless($object, 404);

        $disk = Storage::disk($object->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($object->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }

    // --- Geração em lote ---

    public function generateForm(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Charges/Generate', [
            'condominiums' => $this->condominiumOptions($tenant->id),
            'types' => Charge::TYPES,
        ]);
    }

    public function generatePreview(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenant->id}",
            'amount' => 'required|numeric|min:0',
        ]);

        $units = Unit::where('tenant_id', $tenant->id)
            ->where('condominium_id', $data['condominium_id'])
            ->with(['block:id,name'])
            ->orderBy('number')
            ->get(['id', 'number', 'block_id', 'condominium_id']);

        // Morador principal ativo por unidade (responsável sugerido).
        $primary = PersonUnitLink::whereIn('unit_id', $units->pluck('id'))
            ->whereNull('end_date')
            ->with('person:id,name')
            ->orderByDesc('is_primary')
            ->get()
            ->groupBy('unit_id');

        $rows = $units->map(function ($u) use ($primary, $data) {
            $link = $primary->get($u->id)?->first();

            return [
                'unit_id' => $u->id,
                'unit_label' => trim(($u->block?->name ? $u->block->name.' · ' : '').$u->number),
                'person_id' => $link?->person_id,
                'person_name' => $link?->person?->name,
                'amount' => (float) $data['amount'],
                'include' => true,
            ];
        })->values();

        return response()->json(['rows' => $rows, 'total' => $rows->count()]);
    }

    public function generateConfirm(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenant->id}",
            'type' => 'required|in:'.implode(',', array_keys(Charge::TYPES)),
            'description' => 'required|string|max:200',
            'reference_month' => 'nullable|date_format:Y-m',
            'due_date' => 'required|date',
            'fine_rate' => 'nullable|numeric|min:0|max:100',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'rows' => 'required|array|min:1',
            'rows.*.unit_id' => "required|uuid|exists:units,id,tenant_id,{$tenant->id}",
            'rows.*.amount' => 'required|numeric|min:0',
            'rows.*.person_id' => 'nullable|uuid',
        ]);

        $condominium = Condominium::where('tenant_id', $tenant->id)->findOrFail($data['condominium_id']);

        $count = $this->service->generateBatch($condominium, [
            'type' => $data['type'],
            'description' => $data['description'],
            'reference_month' => $data['reference_month'] ?? null,
            'due_date' => $data['due_date'],
            'fine_rate' => $data['fine_rate'] ?? 0,
            'interest_rate' => $data['interest_rate'] ?? 0,
        ], $data['rows']);

        return redirect()->route('charges.index')->with('success', "{$count} cobrança(s) gerada(s).");
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'unit_id' => "required|uuid|exists:units,id,tenant_id,{$tenantId}",
            'type' => 'required|in:'.implode(',', array_keys(Charge::TYPES)),
            'description' => 'required|string|max:200',
            'reference_month' => 'nullable|date_format:Y-m',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'fine_rate' => 'nullable|numeric|min:0|max:100',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    private function authorizeTenant(Charge $charge): Charge
    {
        abort_unless($charge->tenant_id === app('tenant')->id, 403);

        return $charge;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    private function formData(string $tenantId): array
    {
        return [
            'condominiums' => $this->condominiumOptions($tenantId),
            'units' => Unit::where('tenant_id', $tenantId)
                ->orderBy('number')
                ->get(['id', 'condominium_id', 'number'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->number, 'condominium_id' => $u->condominium_id]),
            'types' => Charge::TYPES,
        ];
    }
}
