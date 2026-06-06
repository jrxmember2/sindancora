<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonUnitLink;
use App\Models\Pet;
use App\Models\Unit;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza o "roster" de uma unidade a partir do formulário: proprietários, inquilinos e
 * familiares (todos como Person vinculados por tipo) + pets. Faz upsert de pessoa por CPF (não
 * duplica), grava telefones/emails como JSON (o 1º espelha em phone/email principal — usado por
 * WhatsApp/cobranças) e remove vínculos/pets que não vieram no envio. Tudo em transação.
 */
class UnitRosterService
{
    public function sync(Unit $unit, array $data): void
    {
        DB::transaction(function () use ($unit, $data) {
            $keptLinks = [
                ...$this->syncGroup($unit, $data['owners'] ?? [], 'owner', true),
                ...$this->syncGroup($unit, $data['tenants'] ?? [], 'tenant', false),
                ...$this->syncGroup($unit, $data['family'] ?? [], 'dependent', false),
            ];

            PersonUnitLink::where('unit_id', $unit->id)
                ->when($keptLinks, fn ($q) => $q->whereNotIn('id', $keptLinks))
                ->delete();

            $this->syncPets($unit, $data['pets'] ?? []);
            $this->syncVehicles($unit, $data['vehicles'] ?? []);
        });
    }

    /** @return array<int,string> ids dos vínculos mantidos */
    private function syncGroup(Unit $unit, array $items, string $type, bool $firstIsPrimary): array
    {
        $kept = [];
        $index = 0;

        foreach ($items as $item) {
            if (trim($item['name'] ?? '') === '') {
                continue; // ignora linhas em branco
            }

            $person = $this->upsertPerson($unit->tenant_id, $item);

            $link = PersonUnitLink::firstOrNew([
                'unit_id' => $unit->id,
                'person_id' => $person->id,
                'type' => $type,
            ]);
            $link->tenant_id = $unit->tenant_id;
            $link->is_primary = $firstIsPrimary && $index === 0;
            $link->end_date = null;
            if (! $link->exists) {
                $link->start_date = now();
            }
            $link->save();

            $kept[] = $link->id;
            $index++;
        }

        return $kept;
    }

    private function upsertPerson(string $tenantId, array $item): Person
    {
        $phones = $this->cleanPhones($item['phones'] ?? []);
        $emails = $this->cleanEmails($item['emails'] ?? []);
        $cpf = preg_replace('/\D/', '', (string) ($item['cpf'] ?? '')) ?: null;

        $attrs = [
            'tenant_id' => $tenantId,
            'name' => trim($item['name']),
            'birth_date' => $this->parseDate($item['birth_date'] ?? null),
            'phones' => $phones,
            'emails' => $emails,
            'phone' => $phones[0] ?? null,   // principal (integrações)
            'email' => $emails[0] ?? null,
        ];

        // Com CPF: reaproveita a pessoa existente (respeita o unique tenant+cpf, evita duplicar).
        if ($cpf) {
            $person = Person::firstOrNew(['tenant_id' => $tenantId, 'cpf' => $cpf]);
            $person->fill(array_merge($attrs, ['cpf' => $cpf]))->save();

            return $person;
        }

        // Sem CPF, mas com id (edição) → atualiza a mesma pessoa.
        if (! empty($item['id'])) {
            $person = Person::where('tenant_id', $tenantId)->find($item['id']);
            if ($person) {
                $person->fill($attrs)->save();

                return $person;
            }
        }

        return Person::create($attrs);
    }

    private function syncPets(Unit $unit, array $pets): void
    {
        $kept = [];

        foreach ($pets as $pet) {
            if (trim($pet['name'] ?? '') === '') {
                continue;
            }

            $attrs = [
                'tenant_id' => $unit->tenant_id,
                'unit_id' => $unit->id,
                'name' => trim($pet['name']),
                'species' => in_array($pet['species'] ?? '', array_keys(Pet::SPECIES), true) ? $pet['species'] : 'other',
                'breed' => $pet['breed'] ?? null,
                'notes' => $pet['notes'] ?? null,
            ];

            if (! empty($pet['id']) && ($existing = Pet::where('unit_id', $unit->id)->find($pet['id']))) {
                $existing->update($attrs);
                $kept[] = $existing->id;

                continue;
            }

            $kept[] = Pet::create($attrs)->id;
        }

        Pet::where('unit_id', $unit->id)
            ->when($kept, fn ($q) => $q->whereNotIn('id', $kept))
            ->delete();
    }

    private function syncVehicles(Unit $unit, array $vehicles): void
    {
        $kept = [];

        foreach ($vehicles as $vehicle) {
            // Linha em branco (sem placa nem marca/modelo) é ignorada.
            if (trim($vehicle['plate'] ?? '') === '' && trim($vehicle['brand_model'] ?? '') === '') {
                continue;
            }

            $attrs = [
                'tenant_id' => $unit->tenant_id,
                'unit_id' => $unit->id,
                'type' => in_array($vehicle['type'] ?? '', array_keys(Vehicle::TYPES), true) ? $vehicle['type'] : 'car',
                'plate' => $vehicle['plate'] ? mb_strtoupper(trim($vehicle['plate'])) : null,
                'brand_model' => $vehicle['brand_model'] ?? null,
                'color' => $vehicle['color'] ?? null,
                'parking_spot' => $vehicle['parking_spot'] ?? null,
                'notes' => $vehicle['notes'] ?? null,
            ];

            if (! empty($vehicle['id']) && ($existing = Vehicle::where('unit_id', $unit->id)->find($vehicle['id']))) {
                $existing->update($attrs);
                $kept[] = $existing->id;

                continue;
            }

            $kept[] = Vehicle::create($attrs)->id;
        }

        Vehicle::where('unit_id', $unit->id)
            ->when($kept, fn ($q) => $q->whereNotIn('id', $kept))
            ->delete();
    }

    /** @param array<int,string> $phones @return array<int,string> dígitos, sem vazios/duplicados */
    private function cleanPhones(array $phones): array
    {
        return collect($phones)
            ->map(fn ($p) => preg_replace('/\D/', '', (string) $p))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int,string> $emails @return array<int,string> válidos, minúsculos, sem duplicados */
    private function cleanEmails(array $emails): array
    {
        return collect($emails)
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter(fn ($e) => str_contains($e, '@'))
            ->unique()
            ->values()
            ->all();
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                // tenta o próximo formato
            }
        }

        return null;
    }
}
