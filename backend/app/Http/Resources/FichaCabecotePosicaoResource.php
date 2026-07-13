<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FichaCabecotePosicaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cabecote' => $this->cabecote,
            'sentido' => $this->sentido,
            'largura_mm' => $this->largura_mm,
            'deslocamento_mm' => $this->deslocamento_mm,
            'altura_cabecote_mm' => $this->altura_cabecote_mm,
            'obs' => $this->obs,
        ];
    }
}
