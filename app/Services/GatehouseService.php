<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisitorAuthorization;
use App\Models\VisitorVisit;
use App\Notifications\VisitorArrived;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras da Portaria: pré-autorização de visitantes (com token/QR), validação na
 * portaria e registro de entradas/saídas (log de acesso).
 */
class GatehouseService
{
    /** Cria uma pré-autorização de visitante e gera o token único (apresentado/validado na portaria). */
    public function authorize(array $data, ?User $creator = null): VisitorAuthorization
    {
        return VisitorAuthorization::create([
            'condominium_id' => $data['condominium_id'],
            'unit_id' => $data['unit_id'],
            'created_by' => $creator?->id,
            'visitor_name' => $data['visitor_name'],
            'visitor_document' => $data['visitor_document'] ?? null,
            'visitor_phone' => $data['visitor_phone'] ?? null,
            'type' => $data['type'] ?? 'single',
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'token' => $this->generateToken(),
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Revoga uma autorização (não pode mais ser usada na portaria). */
    public function revoke(VisitorAuthorization $authorization): void
    {
        $authorization->update(['status' => 'revoked']);
    }

    /** Localiza uma autorização pelo token (case-insensitive), dentro do tenant atual. */
    public function findByToken(string $token): ?VisitorAuthorization
    {
        return VisitorAuthorization::with(['unit:id,number,condominium_id', 'condominium:id,name'])
            ->whereRaw('UPPER(token) = ?', [Str::upper(trim($token))])
            ->first();
    }

    /**
     * Registra a entrada de um visitante pré-autorizado: cria a visita, vincula a
     * autorização, marca como utilizada (se visita única) e notifica quem autorizou.
     */
    public function checkInAuthorized(VisitorAuthorization $authorization, ?User $porteiro = null): VisitorVisit
    {
        return DB::transaction(function () use ($authorization, $porteiro) {
            $visit = VisitorVisit::create([
                'condominium_id' => $authorization->condominium_id,
                'unit_id' => $authorization->unit_id,
                'authorization_id' => $authorization->id,
                'visitor_name' => $authorization->visitor_name,
                'visitor_document' => $authorization->visitor_document,
                'check_in_at' => Carbon::now(),
                'registered_by' => $porteiro?->id,
            ]);

            // Visita única é consumida; recorrente permanece ativa.
            if ($authorization->type === 'single') {
                $authorization->update(['status' => 'used']);
            }

            $this->notifyResident($authorization);

            return $visit;
        });
    }

    /** Registra a entrada de um visitante avulso (walk-in), sem pré-autorização. */
    public function checkInWalkIn(array $data, ?User $porteiro = null): VisitorVisit
    {
        return VisitorVisit::create([
            'condominium_id' => $data['condominium_id'],
            'unit_id' => $data['unit_id'] ?? null,
            'visitor_name' => $data['visitor_name'],
            'visitor_document' => $data['visitor_document'] ?? null,
            'check_in_at' => Carbon::now(),
            'registered_by' => $porteiro?->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Registra a saída do visitante (fecha a visita). Idempotente. */
    public function checkOut(VisitorVisit $visit): void
    {
        if ($visit->check_out_at === null) {
            $visit->update(['check_out_at' => Carbon::now()]);
        }
    }

    /** Notifica o usuário que autorizou (morador) que o visitante chegou. */
    private function notifyResident(VisitorAuthorization $authorization): void
    {
        $user = $authorization->created_by ? User::find($authorization->created_by) : null;

        if ($user) {
            $user->notify(new VisitorArrived($authorization));
        }
    }

    /** Gera um token curto e único para a autorização (apresentado como QR/código). */
    private function generateToken(): string
    {
        do {
            $token = Str::upper(Str::random(8));
        } while (VisitorAuthorization::where('token', $token)->withTrashed()->exists());

        return $token;
    }
}
