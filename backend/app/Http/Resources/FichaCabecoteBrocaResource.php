<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FichaCabecoteBrocaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cabecote' => $this->cabecote,
            'sentido' => $this->sentido,
            'posicao' => $this->posicao,
            'broca_id' => $this->broca_id,
            'broca' => $this->whenLoaded('broca', fn () => new BrocaResource($this->broca)),
            'passante' => (bool) $this->passante,
            'profundidade_mm' => $this->profundidade_mm,
            'agregado' => $this->agregado,
            'obs' => $this->obs,
        ];
    }
}
