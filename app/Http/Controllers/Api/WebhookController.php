<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Services\Payments\AsaasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private readonly AsaasService $asaas) {}

    /**
     * Webhook do Asaas. Resolve o tenant pela própria cobrança (externalReference = charge.id),
     * valida o token configurado pelo tenant e concilia. Responde 200 quando processado;
     * o Asaas re-tenta em respostas não-2xx (a conciliação é idempotente).
     */
    public function asaas(Request $request): JsonResponse
    {
        $payment = $request->input('payment', []);

        $charge = $this->resolveCharge($payment);
        if (! $charge) {
            // Sem cobrança correspondente não há o que conciliar; devolve 200 para não gerar retry infinito.
            Log::info('Asaas webhook ignorado: cobrança não localizada', ['payment' => $payment['id'] ?? null]);

            return response()->json(['status' => 'ignored']);
        }

        $setting = $charge->tenant->paymentSetting()->first();
        $expected = $setting?->webhook_token;

        if (blank($expected) || ! hash_equals($expected, (string) $request->header('asaas-access-token'))) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $this->asaas->reconcile($request->all());

        return response()->json(['status' => 'ok']);
    }

    private function resolveCharge(array $payment): ?Charge
    {
        $query = Charge::withoutGlobalScope('tenant')->with('tenant');

        if (! empty($payment['externalReference'])) {
            $charge = (clone $query)->find($payment['externalReference']);
            if ($charge) {
                return $charge;
            }
        }

        if (! empty($payment['id'])) {
            return $query->where('gateway_payment_id', $payment['id'])->first();
        }

        return null;
    }
}
