<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Condominium;
use App\Models\Unit;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    public function index(Request $request, Condominium $condominium): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $units = $condominium->units()
            ->with(['block', 'activeLinks.person'])
            ->when($request->search, fn ($q, $s) => $q->where('number', 'ilike', "%{$s}%"))
            ->when($request->block_id, fn ($q, $b) => $q->where('block_id', $b))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('block_id')
            ->orderBy('number')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'condominium' => $condominium->only('id', 'name'),
            'units' => $units,
            'blocks' => $condominium->blocks()->orderBy('name')->get(['id', 'name']),
            'typeLabels' => Unit::typeLabels(),
            'statusLabels' => Unit::statusLabels(),
            'filters' => $request->only(['search', 'block_id', 'status', 'type']),
        ]);
    }

    public function create(Condominium $condominium): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);
        $this->planLimitService->check($tenant, 'units');

        return Inertia::render('Units/Create', [
            'condominium' => $condominium->only('id', 'name'),
            'blocks' => $condominium->blocks()->orderBy('name')->get(['id', 'name']),
            'typeLabels' => Unit::typeLabels(),
            'statusLabels' => Unit::statusLabels(),
        ]);
    }

    public function store(Request $request, Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);
        $this->planLimitService->check($tenant, 'units');

        $data = $request->validate([
            'number' => 'required|string|max:20',
            'block_id' => 'nullable|uuid|exists:blocks,id',
            'floor' => 'nullable|integer',
            'type' => 'required|in:apartment,house,commercial,garage,storage',
            'area_m2' => 'nullable|numeric|min:0',
            'fraction' => 'nullable|numeric|min:0',
            'status' => 'required|in:occupied,vacant,under_renovation',
        ]);

        $this->validateUniqueNumber($condominium, $data['number'], $data['block_id'] ?? null);

        $unit = $condominium->units()->create(array_merge($data, ['tenant_id' => $tenant->id]));
        $this->planLimitService->increment($tenant, 'units');

        return redirect()->route('condominiums.units.index', $condominium)->with('success', "Unidade {$unit->number} criada.");
    }

    public function edit(Condominium $condominium, Unit $unit): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $unit->condominium_id === $condominium->id, 403);

        return Inertia::render('Units/Edit', [
            'condominium' => $condominium->only('id', 'name'),
            'unit' => $unit,
            'blocks' => $condominium->blocks()->orderBy('name')->get(['id', 'name']),
            'typeLabels' => Unit::typeLabels(),
            'statusLabels' => Unit::statusLabels(),
        ]);
    }

    public function update(Request $request, Condominium $condominium, Unit $unit): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $unit->condominium_id === $condominium->id, 403);

        $data = $request->validate([
            'number' => 'required|string|max:20',
            'block_id' => 'nullable|uuid|exists:blocks,id',
            'floor' => 'nullable|integer',
            'type' => 'required|in:apartment,house,commercial,garage,storage',
            'area_m2' => 'nullable|numeric|min:0',
            'fraction' => 'nullable|numeric|min:0',
            'status' => 'required|in:occupied,vacant,under_renovation',
        ]);

        if ($data['number'] !== $unit->number || ($data['block_id'] ?? null) !== $unit->block_id) {
            $this->validateUniqueNumber($condominium, $data['number'], $data['block_id'] ?? null, $unit->id);
        }

        $unit->update($data);

        return redirect()->route('condominiums.units.index', $condominium)->with('success', "Unidade {$unit->number} atualizada.");
    }

    public function destroy(Condominium $condominium, Unit $unit): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id && $unit->condominium_id === $condominium->id, 403);

        $unit->delete();
        $this->planLimitService->decrement($tenant, 'units');

        return redirect()->route('condominiums.units.index', $condominium)->with('success', 'Unidade excluída.');
    }

    // --- Import CSV ---

    public function importForm(Condominium $condominium): Response
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        return Inertia::render('Units/Import', [
            'condominium' => $condominium->only('id', 'name'),
            'blocks' => $condominium->blocks()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function importPreview(Request $request, Condominium $condominium): JsonResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('file')->getRealPath();
        $rows = [];
        $errors = [];
        $lineNum = 0;

        $blocks = $condominium->blocks()->pluck('id', 'name')->toArray();
        $existingNumbers = $condominium->units()->pluck('number')->toArray();

        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            while (($line = fgetcsv($handle, 1000, ';')) !== false) {
                $lineNum++;

                if ($lineNum === 1) {
                    $header = array_map('trim', $line);
                    continue;
                }

                if (empty(array_filter($line))) continue;

                $row = array_combine($header ?? [], array_pad($line, count($header ?? []), null));
                $number = trim($row['numero'] ?? $row['number'] ?? '');
                $blockName = trim($row['bloco'] ?? $row['block'] ?? '');
                $floor = trim($row['andar'] ?? $row['floor'] ?? '');
                $type = trim($row['tipo'] ?? $row['type'] ?? 'apartment');
                $area = trim($row['area'] ?? $row['area_m2'] ?? '');

                $rowErrors = [];
                if (empty($number)) $rowErrors[] = 'Número obrigatório';
                if (in_array($number, $existingNumbers)) $rowErrors[] = "Unidade {$number} já existe";

                $blockId = null;
                if ($blockName) {
                    $blockId = $blocks[$blockName] ?? null;
                    if (! $blockId) $rowErrors[] = "Bloco \"{$blockName}\" não encontrado";
                }

                $rows[] = [
                    'line' => $lineNum,
                    'number' => $number,
                    'block_name' => $blockName,
                    'block_id' => $blockId,
                    'floor' => is_numeric($floor) ? (int) $floor : null,
                    'type' => in_array($type, array_keys(Unit::typeLabels())) ? $type : 'apartment',
                    'area_m2' => is_numeric($area) ? (float) $area : null,
                    'status' => 'vacant',
                    'errors' => $rowErrors,
                    'valid' => empty($rowErrors),
                ];
            }
            fclose($handle);
        }

        return response()->json([
            'rows' => $rows,
            'total' => count($rows),
            'valid' => count(array_filter($rows, fn ($r) => $r['valid'])),
            'invalid' => count(array_filter($rows, fn ($r) => ! $r['valid'])),
        ]);
    }

    public function importConfirm(Request $request, Condominium $condominium): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($condominium->tenant_id === $tenant->id, 403);

        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.number' => 'required|string',
            'rows.*.type' => 'required|string',
            'rows.*.status' => 'required|string',
        ]);

        $this->planLimitService->check($tenant, 'units', count($request->rows));

        $created = 0;
        foreach ($request->rows as $row) {
            $condominium->units()->create([
                'tenant_id' => $tenant->id,
                'number' => $row['number'],
                'block_id' => $row['block_id'] ?? null,
                'floor' => $row['floor'] ?? null,
                'type' => $row['type'],
                'area_m2' => $row['area_m2'] ?? null,
                'status' => $row['status'] ?? 'vacant',
            ]);
            $created++;
        }

        $this->planLimitService->incrementBy($tenant, 'units', $created);

        return redirect()->route('condominiums.units.index', $condominium)
            ->with('success', "{$created} unidades importadas com sucesso.");
    }

    private function validateUniqueNumber(Condominium $condominium, string $number, ?string $blockId, ?string $excludeId = null): void
    {
        $query = $condominium->units()->where('number', $number)->where('block_id', $blockId);
        if ($excludeId) $query->where('id', '!=', $excludeId);

        abort_if($query->exists(), 422, "Unidade {$number} já existe" . ($blockId ? ' neste bloco' : '') . '.');
    }
}
