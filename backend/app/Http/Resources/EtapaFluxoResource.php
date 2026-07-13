<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EtapaFluxoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ordem' => $this->ordem,
            'ativa' => (bool) $this->ativa,
            'requer_config_cabecote' => (bool) $this->requer_config_cabecote,
        ];
    }
}
