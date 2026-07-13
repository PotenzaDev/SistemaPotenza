<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrocaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'espessura_mm' => (float) $this->espessura_mm,
            'rotacao' => $this->rotacao,
            'altura_mm' => (float) $this->altura_mm,
            'furo_passante' => (bool) $this->furo_passante,
            'ativo' => (bool) $this->ativo,
        ];
    }
}
