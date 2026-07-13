<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChamadaSuporteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'origem'    => $this->origem ?? 'operario',
            'criado_em' => $this->created_at->toISOString(),
            'maquina'   => $this->whenLoaded('maquina', fn () => $this->maquina ? [
                'id'   => $this->maquina->id,
                'nome' => $this->maquina->nome,
            ] : null),
            'operario'  => $this->whenLoaded('operario', fn () => $this->operario ? [
                'id'   => $this->operario->id,
                'nome' => $this->operario->nome,
            ] : null),
        ];
    }
}
