<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FichaCabecoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produto_peca_id' => $this->produto_peca_id,
            'maquina_id' => $this->maquina_id,
            'operario_id' => $this->operario_id,
            'data' => $this->data?->format('Y-m-d'),
            'top_esquerdo_mm' => $this->top_esquerdo_mm,
            'top_direito_mm' => $this->top_direito_mm,
            'quantidade_pecas_vez' => $this->quantidade_pecas_vez,
            'velocidade_trabalho' => $this->velocidade_trabalho,
            'observacao' => $this->observacao,
            'maquina' => $this->whenLoaded('maquina', fn () => $this->maquina ? new MaquinaResource($this->maquina) : null),
            'operario' => $this->whenLoaded('operario', fn () => $this->operario ? new OperarioResource($this->operario) : null),
            'posicoes_cabecote' => $this->whenLoaded('posicoesCabecote', fn () => FichaCabecotePosicaoResource::collection($this->posicoesCabecote)),
            'posicoes_broca' => $this->whenLoaded('posicoesBroca', fn () => FichaCabecoteBrocaResource::collection($this->posicoesBroca)),
            'posicoes_cabecote_count' => $this->whenCounted('posicoesCabecote'),
            'posicoes_broca_count' => $this->whenCounted('posicoesBroca'),
            'completa' => $this->completa,
        ];
    }
}
