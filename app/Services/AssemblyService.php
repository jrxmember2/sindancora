<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\AssemblyAgendaItem;
use App\Models\AssemblyAttendance;
use App\Models\AssemblyVote;
use App\Models\Unit;
use App\Services\AI\AiException;
use App\Services\AI\ClaudeClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssemblyService
{
    public function __construct(private readonly ClaudeClient $claude) {}

    /** Registra a presença das unidades do morador (idempotente). */
    public function registerAttendance(Assembly $assembly, array $unitIds, ?string $personId): void
    {
        foreach ($unitIds as $unitId) {
            AssemblyAttendance::firstOrCreate(
                ['assembly_id' => $assembly->id, 'unit_id' => $unitId],
                ['tenant_id' => $assembly->tenant_id, 'person_id' => $personId],
            );
        }
    }

    /**
     * Registra/atualiza o voto de cada unidade do morador num item (um voto por unidade).
     * Só com a votação aberta.
     */
    public function castVote(Assembly $assembly, AssemblyAgendaItem $item, string $optionId, array $unitIds, ?string $personId): void
    {
        abort_unless($assembly->isOpen(), 422, 'A votação não está aberta.');
        abort_unless($item->options()->whereKey($optionId)->exists(), 422, 'Opção inválida.');

        DB::transaction(function () use ($assembly, $item, $optionId, $unitIds, $personId) {
            foreach ($unitIds as $unitId) {
                AssemblyVote::updateOrCreate(
                    ['agenda_item_id' => $item->id, 'unit_id' => $unitId],
                    [
                        'tenant_id' => $assembly->tenant_id,
                        'assembly_id' => $assembly->id,
                        'option_id' => $optionId,
                        'person_id' => $personId,
                    ],
                );
            }
            // Presença implícita ao votar.
            $this->registerAttendance($assembly, $unitIds, $personId);
        });
    }

    /**
     * Apuração: por item, contagem de votos por opção + total; presença e total de unidades.
     *
     * @return array<string,mixed>
     */
    public function results(Assembly $assembly): array
    {
        $assembly->loadMissing('items.options');

        $totalUnits = Unit::where('condominium_id', $assembly->condominium_id)->count();
        $present = AssemblyAttendance::where('assembly_id', $assembly->id)->count();

        $items = $assembly->items->map(function (AssemblyAgendaItem $item) {
            $counts = AssemblyVote::where('agenda_item_id', $item->id)
                ->select('option_id', DB::raw('count(*) as total'))
                ->groupBy('option_id')
                ->pluck('total', 'option_id');

            $total = (int) $counts->sum();

            $options = $item->options->map(fn ($o) => [
                'id' => $o->id,
                'label' => $o->label,
                'votes' => (int) ($counts[$o->id] ?? 0),
                'percent' => $total > 0 ? round(((int) ($counts[$o->id] ?? 0)) / $total * 100, 1) : 0,
            ])->values();

            $winner = $options->sortByDesc('votes')->first();

            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'total_votes' => $total,
                'options' => $options,
                'winner' => $total > 0 ? $winner['label'] : null,
            ];
        })->values();

        return [
            'total_units' => $totalUnits,
            'present_units' => $present,
            'items' => $items,
        ];
    }

    /** Gera a ata: redigida pela IA se configurada; senão, modelo determinístico. */
    public function generateMinutes(Assembly $assembly): string
    {
        $assembly->loadMissing('condominium:id,name');
        $results = $this->results($assembly);
        $summary = $this->resultsToText($assembly, $results);

        $minutes = $this->claude->configured()
            ? $this->aiMinutes($assembly, $summary)
            : $this->templateMinutes($assembly, $summary);

        $assembly->forceFill([
            'minutes' => $minutes,
            'minutes_generated_at' => Carbon::now(),
        ])->save();

        return $minutes;
    }

    private function aiMinutes(Assembly $assembly, string $summary): string
    {
        $system = 'Você redige atas de assembleia de condomínio em português do Brasil, em tom formal e objetivo. '
            .'Produza a ata completa (abertura, ordem do dia, deliberações com os resultados de votação e encerramento) '
            .'usando SOMENTE os dados fornecidos. Não invente participantes nem números.';

        try {
            return $this->claude->complete($system, [[
                'role' => 'user',
                'content' => "Redija a ata desta assembleia:\n\n".$summary,
            ]], 4096);
        } catch (AiException) {
            return $this->templateMinutes($assembly, $summary);
        }
    }

    private function templateMinutes(Assembly $assembly, string $summary): string
    {
        return "ATA DA ASSEMBLEIA\n\n".$summary;
    }

    private function resultsToText(Assembly $assembly, array $results): string
    {
        $lines = [];
        $lines[] = "Condomínio: {$assembly->condominium?->name}";
        $lines[] = "Assembleia: {$assembly->title}";
        if ($assembly->scheduled_at) {
            $lines[] = 'Data: '.$assembly->scheduled_at->format('d/m/Y H:i');
        }
        if ($assembly->description) {
            $lines[] = "Descrição: {$assembly->description}";
        }
        $lines[] = "Presença: {$results['present_units']} de {$results['total_units']} unidade(s).";
        $lines[] = '';
        $lines[] = 'ORDEM DO DIA E DELIBERAÇÕES:';

        foreach ($results['items'] as $i => $item) {
            $n = $i + 1;
            $lines[] = "{$n}. {$item['title']}".($item['description'] ? " — {$item['description']}" : '');
            foreach ($item['options'] as $opt) {
                $lines[] = "   - {$opt['label']}: {$opt['votes']} voto(s) ({$opt['percent']}%)";
            }
            $lines[] = '   Resultado: '.($item['winner'] ?? 'sem votos').". Total de votos: {$item['total_votes']}.";
        }

        return implode("\n", $lines);
    }
}
