<?php

namespace App\Services;

use App\Models\Occurrence;
use App\Models\Person;
use App\Models\PersonUnitLink;
use App\Models\PublicSubmission;
use App\Models\StorageObject;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PublicSubmissionReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Modera os envios públicos (auto-cadastro de morador / ocorrência) gerados pelos links/QR.
 * Aprovar um cadastro cria/reaproveita a Pessoa e o vínculo com a unidade (com convite opcional
 * ao portal); aprovar uma ocorrência cria a Ocorrência definitiva. Tudo em transação e com
 * trilha de auditoria na própria submissão.
 */
class PublicSubmissionService
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly OccurrenceService $occurrences,
        private readonly StorageService $storage,
    ) {}

    /**
     * Aprova um auto-cadastro de morador: garante a Pessoa (upsert por documento), vincula à
     * unidade e, opcionalmente, dispara o convite ao portal pelos canais escolhidos.
     *
     * @param array{invite?: bool, channels?: array<int,string>} $options
     */
    public function approveResidentSignup(PublicSubmission $submission, array $options, User $reviewer): Person
    {
        $this->assertPending($submission, 'resident_signup');

        $payload = $submission->payload ?? [];
        $relation = in_array($payload['relation'] ?? null, ['owner', 'tenant', 'dependent'], true)
            ? $payload['relation']
            : 'owner';

        $unit = $this->resolveUnit($submission, $payload['unit_id'] ?? null);

        $person = DB::transaction(function () use ($submission, $payload, $relation, $unit, $reviewer) {
            $person = $this->upsertPerson($submission);

            // Não duplica o vínculo ativo da pessoa com a mesma unidade.
            $existing = PersonUnitLink::where('person_id', $person->id)
                ->where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->first();

            if (! $existing) {
                PersonUnitLink::create([
                    'tenant_id' => $submission->tenant_id,
                    'person_id' => $person->id,
                    'unit_id' => $unit->id,
                    'type' => $relation,
                    'is_primary' => $relation === 'owner',
                    'start_date' => now()->toDateString(),
                ]);
            }

            $submission->forceFill([
                'status' => 'approved',
                'person_id' => $person->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            return $person;
        });

        // Convite ao portal é opcional e acontece fora da transação (envia e-mail/WhatsApp).
        if (($options['invite'] ?? false) && $person->email) {
            $channels = array_values(array_intersect($options['channels'] ?? ['email'], ['email', 'whatsapp'])) ?: ['email'];
            $this->invitations->invite($person, $channels);
        }

        return $person;
    }

    /**
     * Aprova uma ocorrência pública: cria a Ocorrência definitiva (aberta) com o contato do
     * solicitante anexado à descrição e o prazo de SLA calculado pela prioridade.
     */
    public function approveOccurrence(PublicSubmission $submission, array $options, User $reviewer): Occurrence
    {
        $this->assertPending($submission, 'occurrence');

        $payload = $submission->payload ?? [];
        $unit = isset($payload['unit_id']) ? $this->resolveUnit($submission, $payload['unit_id'], required: false) : null;

        $category = in_array($payload['category'] ?? null, array_keys(Occurrence::CATEGORIES), true)
            ? $payload['category']
            : 'other';

        $priority = in_array($options['priority'] ?? null, array_keys(Occurrence::PRIORITIES), true)
            ? $options['priority']
            : 'normal';

        return DB::transaction(function () use ($submission, $payload, $unit, $category, $priority, $reviewer) {
            $occurrence = Occurrence::create([
                'tenant_id' => $submission->tenant_id,
                'condominium_id' => $submission->condominium_id,
                'unit_id' => $unit?->id,
                'title' => mb_substr((string) ($payload['title'] ?? 'Ocorrência pública'), 0, 200),
                'description' => $this->occurrenceDescription($submission, $payload),
                'category' => $category,
                'priority' => $priority,
                'status' => 'open',
                'created_by' => $reviewer->id,
            ]);

            $this->occurrences->ensureDueAt($occurrence);

            // Re-aponta as fotos do envio para a ocorrência (aparecem como anexos dela).
            StorageObject::where('tenant_id', $submission->tenant_id)
                ->where('entity_type', PublicSubmission::ATTACHMENT_ENTITY)
                ->where('entity_id', $submission->id)
                ->whereNull('deleted_at')
                ->update([
                    'entity_type' => Occurrence::ATTACHMENT_ENTITY,
                    'entity_id' => $occurrence->id,
                ]);

            $submission->forceFill([
                'status' => 'approved',
                'occurrence_id' => $occurrence->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            return $occurrence;
        });
    }

    /** Reprova um envio público, registrando o motivo e o revisor e liberando anexos/cota. */
    public function reject(PublicSubmission $submission, ?string $notes, User $reviewer): void
    {
        $this->assertPending($submission);

        // Libera as fotos do envio (soft delete → lixeira) para não consumir cota indevidamente.
        $submission->loadMissing('attachments');
        foreach ($submission->attachments as $attachment) {
            $this->storage->delete($attachment);
        }

        $submission->forceFill([
            'status' => 'rejected',
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /** Avisa os gestores (papéis de painel) do tenant sobre um novo envio aguardando moderação. */
    public function notifyManagers(PublicSubmission $submission): void
    {
        $managers = User::where('tenant_id', $submission->tenant_id)
            ->where('status', 'active')
            ->whereHas('userRoles.role', fn ($q) => $q->whereIn('name', User::PANEL_ROLES))
            ->get();

        if ($managers->isNotEmpty()) {
            Notification::send($managers, new PublicSubmissionReceived($submission));
        }
    }

    private function assertPending(PublicSubmission $submission, ?string $type = null): void
    {
        if (! $submission->isPending()) {
            throw ValidationException::withMessages(['status' => 'Este envio já foi moderado.']);
        }

        if ($type && $submission->type !== $type) {
            throw ValidationException::withMessages(['type' => 'Tipo de envio incompatível com esta ação.']);
        }
    }

    /** Resolve a unidade do envio garantindo que pertence ao mesmo condomínio/tenant. */
    private function resolveUnit(PublicSubmission $submission, ?string $unitId, bool $required = true): ?Unit
    {
        if (! $unitId) {
            if ($required) {
                throw ValidationException::withMessages(['unit_id' => 'Selecione a unidade para vincular.']);
            }

            return null;
        }

        $unit = Unit::where('id', $unitId)
            ->where('tenant_id', $submission->tenant_id)
            ->where('condominium_id', $submission->condominium_id)
            ->first();

        if (! $unit && $required) {
            throw ValidationException::withMessages(['unit_id' => 'Unidade inválida para este condomínio.']);
        }

        return $unit;
    }

    /** Reaproveita a Pessoa pelo documento (CPF/CNPJ) dentro do tenant ou cria uma nova. */
    private function upsertPerson(PublicSubmission $submission): Person
    {
        $document = preg_replace('/\D/', '', (string) $submission->document) ?: null;
        $payload = $submission->payload ?? [];

        $person = null;
        if ($document) {
            $person = Person::where('tenant_id', $submission->tenant_id)
                ->where('cpf', $document)
                ->first();
        }

        $attributes = [
            'person_type' => ($payload['person_type'] ?? 'individual') === 'company' ? 'company' : 'individual',
            'name' => $submission->name,
            'email' => $submission->email,
            'phone' => $submission->phone,
        ];

        if ($person) {
            // Completa apenas os campos vazios para não sobrescrever dados já curados.
            $person->fill(array_filter([
                'email' => $person->email ?: $attributes['email'],
                'phone' => $person->phone ?: $attributes['phone'],
            ]));
            $person->save();

            return $person;
        }

        return Person::create(array_merge($attributes, [
            'tenant_id' => $submission->tenant_id,
            'cpf' => $document,
        ]));
    }

    /** Monta a descrição da ocorrência incluindo o contato informado no envio público. */
    private function occurrenceDescription(PublicSubmission $submission, array $payload): string
    {
        $lines = [trim((string) ($payload['description'] ?? ''))];

        $contact = array_filter([
            $submission->name ? "Nome: {$submission->name}" : null,
            $submission->phone ? "Telefone: {$submission->phone}" : null,
            $submission->email ? "E-mail: {$submission->email}" : null,
            ! empty($payload['unit_label']) ? "Unidade informada: {$payload['unit_label']}" : null,
        ]);

        if ($contact) {
            $lines[] = '';
            $lines[] = '— Enviado pelo link público —';
            $lines = array_merge($lines, $contact);
        }

        return trim(implode("\n", $lines));
    }
}
