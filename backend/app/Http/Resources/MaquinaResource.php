<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaquinaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'etapa_fluxo_id' => $this->etapa_fluxo_id,
            'nome' => $this->nome,
            'codigo' => $this->codigo,
            'ano' => $this->ano,
            'descricao' => $this->descricao,
            'foto_url' => $this->foto_url,
            'ativa' => (bool) $this->ativa,
            'prioridade' => $this->prioridade,
            'etapa_fluxo' => $this->whenLoaded('etapaFluxo'),
            'configuracao_cabecote' => $this->whenLoaded('configuracaoCabecote'),
            'regra_maquina' => $this->whenLoaded('regraMaquina'),
        ];
    }
}
