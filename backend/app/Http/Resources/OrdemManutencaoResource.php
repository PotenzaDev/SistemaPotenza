<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdemManutencaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maquina' => [
                'id' => $this->maquina->id,
                'nome' => $this->maquina->nome,
                'etapa_fluxo' => $this->maquina->etapaFluxo ? [
                    'id' => $this->maquina->etapaFluxo->id,
                    'nome' => $this->maquina->etapaFluxo->nome,
                ] : null,
            ],
            'solicitante' => $this->solicitante,
            'motivo' => $this->motivo,
            'prioridade' => $this->prioridade,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'solicitado_em' => $this->solicitado_em?->toISOString(),
            'atendido_em' => $this->atendido_em?->toISOString(),
            'concluido_em' => $this->concluido_em?->toISOString(),
            'pecas' => $this->pecas->map(fn ($peca) => [
                'id' => $peca->id,
                'descricao' => $peca->descricao,
                'quantidade' => (float) $peca->quantidade,
                'preco_unitario' => (float) $peca->preco_unitario,
            ]),
            'servicos' => $this->servicos->map(fn ($servico) => [
                'id' => $servico->id,
                'servico' => $servico->servico,
                'descricao' => $servico->descricao,
                'valor' => (float) $servico->valor,
                'data' => $servico->data?->toDateString(),
            ]),
        ];
    }
}
