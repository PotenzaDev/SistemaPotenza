<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PausaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'fase'             => $this->fase,
            'motivo_id'        => $this->motivo_pausa_id,
            'motivo'           => $this->whenLoaded('motivoPausa', fn () => $this->motivoPausa->nome),
            'is_sistema'       => $this->whenLoaded('motivoPausa', fn () => $this->motivoPausa->is_sistema, false),
            'inicio'           => $this->inicio?->toIso8601String(),
            'fim'              => $this->fim?->toIso8601String(),
            'duracao_segundos' => $this->duracao_segundos,
        ];
    }
}
