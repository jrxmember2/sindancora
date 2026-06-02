<?php

namespace App\Jobs;

use App\Models\Charge;
use App\Services\Payments\AsaasException;
use App\Services\Payments\AsaasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Emite boleto/PIX de uma cobrança no gateway em background. Útil na emissão em lote, evitando
 * estourar o timeout HTTP com N chamadas síncronas ao Asaas.
 */
class IssueGatewayCharge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Charge $charge) {}

    public function handle(AsaasService $asaas): void
    {
        try {
            $asaas->issueCharge($this->charge);
        } catch (AsaasException $e) {
            // Falha de negócio (sem CPF, integração desligada) não deve re-tentar indefinidamente.
            Log::warning('Falha ao emitir cobrança no Asaas', ['charge' => $this->charge->id, 'error' => $e->getMessage()]);
        }
    }
}
