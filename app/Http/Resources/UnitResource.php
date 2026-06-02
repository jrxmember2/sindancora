<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condominium_id' => $this->condominium_id,
            'block_id' => $this->block_id,
            'number' => $this->number,
            'floor' => $this->floor,
            'type' => $this->type,
            'area_m2' => $this->area_m2,
            'fraction' => $this->fraction,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
