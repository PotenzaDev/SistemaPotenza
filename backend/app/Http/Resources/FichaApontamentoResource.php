<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FichaApontamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cod_peca'         => $this->cod_peca,
            'pilha'            => $this->pilha,
            'qtd_peca'         => $this->qtd_peca,
            'qtd_produzida'    => $this->qtd_produzida,
            'total_pilhas'     => $this->total_pilhas,
            'bipada_at'        => $this->bipada_at?->toIso8601String(),
            'fim_producao'     => $this->fim_producao?->toIso8601String(),
            'duracao_segundos' => $this->duracao_segundos,
            // Incluído ao usar fichasRecentes (apontamento eager-loaded)
            'ordem_lote'       => $this->whenLoaded('apontamento', fn () => $this->apontamento->ordem_lote),
        ];
    }
}
