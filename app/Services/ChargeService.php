<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Condominium;
use App\Models\PersonUnitLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChargeService
{
    public function __construct(
        private readonly StorageService $storage,
        private readonly WebhookService $webhooks,
    ) {}

    /**
     * Gera um lote de cobranças para um condomínio. Cada linha em $rows traz a unidade e o valor
     * (já ajustado na pré-visualização). Todas as cobranças compartilham o mesmo batch_id.
     *
     * @param  array{type:string,reference_month:?string,due_date:string,fine_rate:float,interest_rate:float,description:string}  $meta
     * @param  array<int,array{unit_id:string,amount:float|string,person_id?:?string}>  $rows
     * @return array<int,Charge> cobranças criadas
     */
    public function generateBatch(Condominium $condominium, array $meta, array $rows): array
    {
        $batchId = (string) Str::uuid();

        return DB::transaction(function () use ($condominium, $meta, $rows, $batchId) {
            $created = [];

            foreach ($rows as $row) {
                $created[] = Charge::create([
                    'tenant_id' => $condominium->tenant_id,
                    'condominium_id' => $condominium->id,
                    'unit_id' => $row['unit_id'],
                    'person_id' => $row['person_id'] ?? $this->primaryResidentId($row['unit_id']),
                    'batch_id' => $batchId,
                    'type' => $meta['type'],
                    'description' => $meta['description'],
                    'reference_month' => $meta['reference_month'] ?? null,
                    'amount' => $row['amount'],
                    'due_date' => $meta['due_date'],
                    'fine_rate' => $meta['fine_rate'] ?? 0,
                    'interest_rate' => $meta['interest_rate'] ?? 0,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }

            return $created;
        });
    }

    /**
     * Registra o pagamento de uma cobrança e, opcionalmente, anexa o comprovante.
     *
     * @param  array{paid_at:string,paid_amount:float|string,payment_method?:?string,notes?:?string}  $data
     */
    public function registerPayment(Charge $charge, array $data, ?UploadedFile $receipt = null): Charge
    {
        if ($receipt) {
            $object = $this->storage->upload(
                file: $receipt,
                tenant: app('tenant'),
                entityType: 'charge_receipt',
                entityId: $charge->id,
                visibility: 'tenant',
                condominiumId: $charge->condominium_id,
            );
            $charge->receipt_storage_object_id = $object->id;
        }

        $charge->forceFill([
            'status' => 'paid',
            'paid_at' => $data['paid_at'],
            'paid_amount' => $data['paid_amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'receipt_storage_object_id' => $charge->receipt_storage_object_id,
        ]);

        if (! empty($data['notes'])) {
            $charge->notes = $data['notes'];
        }

        $charge->save();

        $this->webhooks->dispatch($charge->tenant_id, 'charge.paid', $charge->toWebhookArray());

        return $charge;
    }

    /** Morador principal ativo da unidade (para registrar o responsável pela cobrança). */
    private function primaryResidentId(string $unitId): ?string
    {
        return PersonUnitLink::where('unit_id', $unitId)
            ->whereNull('end_date')
            ->orderByDesc('is_primary')
            ->value('person_id');
    }
}
