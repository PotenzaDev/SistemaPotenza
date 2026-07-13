<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessaoTrabalhoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'inicio'  => $this->inicio?->toIso8601String(),
            'fim'     => $this->fim?->toIso8601String(),
            'ativa'   => $this->isAtiva(),
            'pausa_ociosa' => $this->whenLoaded(
                'pausaOciosaAberta',
                fn () => $this->pausaOciosaAberta ? [
                    'id'     => $this->pausaOciosaAberta->id,
                    'motivo' => $this->pausaOciosaAberta->motivoPausa?->nome,
                    'inicio' => $this->pausaOciosaAberta->inicio->toIso8601String(),
                ] : null,
                null
            ),
            'maquina' => $this->whenLoaded('maquina', fn () => [
                'id'          => $this->maquina->id,
                'nome'        => $this->maquina->nome,
                'etapa_fluxo' => $this->when(
                    $this->maquina->relationLoaded('etapaFluxo'),
                    fn () => [
                        'id'    => $this->maquina->etapaFluxo->id,
                        'nome'  => $this->maquina->etapaFluxo->nome,
                        'ordem' => $this->maquina->etapaFluxo->ordem,
                    ]
                ),
                'regra_maquina' => $this->when(
                    $this->maquina->relationLoaded('regraMaquina') && $this->maquina->regraMaquina,
                    fn () => [
                        'possui_setup'                 => $this->maquina->regraMaquina->possui_setup,
                        'possui_producao'               => $this->maquina->regraMaquina->possui_producao,
                        'permite_multiplas_passagens'   => $this->maquina->regraMaquina->permite_multiplas_passagens,
                        'limite_passagens'              => $this->maquina->regraMaquina->limite_passagens,
                    ]
                ),
            ]),
        ];
    }
}
