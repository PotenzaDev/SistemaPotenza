<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// PausaResource is in the same namespace — no extra use needed

class ApontamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'cod_peca'    => $this->cod_peca,
            'ordem_lote'  => $this->ordem_lote,
            'desc_peca'   => $this->desc_peca,
            'cod_produto' => $this->cod_produto,
            'qtde_total'  => $this->qtde_total,
            'status'      => $this->status,
            'etapa_fluxo' => $this->whenLoaded('etapaFluxo', fn () => [
                'id'   => $this->etapaFluxo->id,
                'nome' => $this->etapaFluxo->nome,
            ]),
            'setup_inicio'              => $this->setup_inicio?->toIso8601String(),
            'setup_fim'                 => $this->setup_fim?->toIso8601String(),
            'setup_duracao_segundos'    => $this->setup_duracao_segundos,
            'producao_inicio'           => $this->producao_inicio?->toIso8601String(),
            'producao_fim'              => $this->producao_fim?->toIso8601String(),
            'producao_duracao_segundos' => $this->producao_duracao_segundos,
            'fichas' => $this->whenLoaded('fichas',
                fn () => FichaApontamentoResource::collection($this->fichas),
                []
            ),
            'pausas' => $this->whenLoaded('pausas',
                fn () => PausaResource::collection($this->pausas),
                []
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
