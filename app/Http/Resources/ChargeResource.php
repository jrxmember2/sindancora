<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condominium_id' => $this->condominium_id,
            'unit_id' => $this->unit_id,
            'person_id' => $this->person_id,
            'type' => $this->type,
            'description' => $this->description,
            'reference_month' => $this->reference_month,
            'amount' => $this->amount,
            'current_amount' => $this->currentAmount(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'fine_rate' => $this->fine_rate,
            'interest_rate' => $this->interest_rate,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_amount' => $this->paid_amount,
            'payment' => [
                'invoice_url' => $this->invoice_url,
                'pix_payload' => $this->pix_payload,
                'bank_slip_line' => $this->bank_slip_line,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
