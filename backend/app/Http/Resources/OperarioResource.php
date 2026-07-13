<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricula' => $this->matricula,
            'cargo' => $this->cargo,
            'etapa_fluxo_id' => $this->etapa_fluxo_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'ativo' => (bool) $this->user->ativo,
            ],
            'etapa_fluxo' => $this->etapaFluxo ? [
                'id' => $this->etapaFluxo->id,
                'nome' => $this->etapaFluxo->nome,
            ] : null,
        ];
    }
}
