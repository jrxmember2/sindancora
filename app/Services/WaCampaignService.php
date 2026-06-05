<?php

namespace App\Services;

use App\Jobs\SendCampaignMessage;
use App\Models\PersonUnitLink;
use App\Models\WaCampaign;
use App\Models\WaCampaignRecipient;
use App\Models\WaOptOut;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Monta e dispara campanhas de WhatsApp em massa. A audiência são moradores (Person com telefone)
 * de um condomínio, opcionalmente segmentados por blocos ou unidades. Exclui a lista de opt-out e
 * deduplica por telefone. O envio é enfileirado com throttle (intervalo entre mensagens) — anti-ban.
 */
class WaCampaignService
{
    /** Conta os destinatários elegíveis (para a prévia no formulário), já sem opt-out e dedup. */
    public function previewCount(string $tenantId, string $condominiumId, string $targetType, ?array $blockIds, ?array $unitIds): int
    {
        return $this->collect($tenantId, $condominiumId, $targetType, $blockIds, $unitIds)->count();
    }

    /** Congela os destinatários da campanha em wa_campaign_recipients. Retorna o total. */
    public function buildRecipients(WaCampaign $campaign): int
    {
        $campaign->recipients()->delete();

        $items = $this->collect(
            $campaign->tenant_id,
            $campaign->condominium_id,
            $campaign->target_type,
            $campaign->block_ids,
            $campaign->unit_ids,
        );

        $now = now();
        $rows = $items->map(fn ($i) => [
            'id' => (string) Str::uuid(),
            'tenant_id' => $campaign->tenant_id,
            'campaign_id' => $campaign->id,
            'person_id' => $i['person_id'],
            'name' => $i['name'],
            'phone' => $i['phone'],
            'status' => 'pending',
            'created_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            WaCampaignRecipient::insert($chunk);
        }

        $campaign->update(['total_recipients' => count($rows)]);

        return count($rows);
    }

    /** Inicia o envio: monta os destinatários (se preciso) e enfileira um job por destinatário. */
    public function start(WaCampaign $campaign): void
    {
        if ($campaign->recipients()->count() === 0) {
            $this->buildRecipients($campaign);
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        if ($campaign->recipients()->where('status', 'pending')->doesntExist()) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);

            return;
        }

        $throttle = max(1, (int) $campaign->throttle_seconds);
        $i = 0;
        $campaign->recipients()->where('status', 'pending')->orderBy('created_at')
            ->chunkById(500, function ($recipients) use (&$i, $throttle) {
                foreach ($recipients as $recipient) {
                    $delay = (int) round($i * $throttle) + random_int(0, 3); // jitter anti-ban
                    SendCampaignMessage::dispatch($recipient->id)->delay(now()->addSeconds($delay));
                    $i++;
                }
            });
    }

    /** Coleta a audiência (person_id, name, phone normalizado), sem opt-out e dedup por telefone. */
    private function collect(string $tenantId, string $condominiumId, string $targetType, ?array $blockIds, ?array $unitIds): Collection
    {
        // withoutGlobalScope: o escopo de tenant adiciona `tenant_id` sem qualificar a tabela e,
        // com os JOINs abaixo (units/persons), causaria "column ambiguous". Filtramos persons.tenant_id.
        $rows = PersonUnitLink::withoutGlobalScope('tenant')
            ->whereNull('person_unit_links.end_date')
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->join('persons', 'persons.id', '=', 'person_unit_links.person_id')
            ->where('persons.tenant_id', $tenantId)
            ->whereNull('persons.deleted_at')
            ->where('units.condominium_id', $condominiumId)
            ->whereNotNull('persons.phone')
            ->when($targetType === 'blocks' && filled($blockIds), fn ($q) => $q->whereIn('units.block_id', $blockIds))
            ->when($targetType === 'units' && filled($unitIds), fn ($q) => $q->whereIn('units.id', $unitIds))
            ->select('persons.id as person_id', 'persons.name', 'persons.phone')
            ->get();

        $optOuts = WaOptOut::where('tenant_id', $tenantId)->pluck('phone')->flip();

        return $rows
            ->map(fn ($r) => [
                'person_id' => $r->person_id,
                'name' => $r->name,
                'phone' => WaOptOut::normalizePhone($r->phone),
            ])
            ->filter(fn ($r) => $r['phone'] && ! $optOuts->has($r['phone']))
            ->unique('phone')
            ->values();
    }
}
