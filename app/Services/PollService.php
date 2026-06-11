<?php

namespace App\Services;

use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Support\Facades\DB;

/**
 * Votação e apuração de enquetes rápidas (1 voto por pessoa). Versão simplificada do AssemblyService.
 */
class PollService
{
    /** Registra/atualiza o voto da pessoa (um por pessoa); só com a enquete aberta. */
    public function castVote(Poll $poll, string $optionId, string $personId): void
    {
        abort_unless($poll->isOpen(), 422, 'A enquete não está aberta.');
        abort_unless($poll->options()->whereKey($optionId)->exists(), 422, 'Opção inválida.');

        PollVote::updateOrCreate(
            ['poll_id' => $poll->id, 'person_id' => $personId],
            ['tenant_id' => $poll->tenant_id, 'option_id' => $optionId],
        );
    }

    /**
     * Apuração: contagem e % por opção + total de votos. O voto da pessoa (se houver) é incluído.
     *
     * @return array<string,mixed>
     */
    public function results(Poll $poll, ?string $personId = null): array
    {
        $poll->loadMissing('options');

        $counts = PollVote::where('poll_id', $poll->id)
            ->select('option_id', DB::raw('count(*) as total'))
            ->groupBy('option_id')
            ->pluck('total', 'option_id');

        $total = (int) $counts->sum();

        $options = $poll->options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'votes' => (int) ($counts[$o->id] ?? 0),
            'percent' => $total > 0 ? round(((int) ($counts[$o->id] ?? 0)) / $total * 100, 1) : 0,
        ])->values();

        $myVote = $personId
            ? PollVote::where('poll_id', $poll->id)->where('person_id', $personId)->value('option_id')
            : null;

        return [
            'total_votes' => $total,
            'options' => $options,
            'my_vote' => $myVote,
        ];
    }
}
