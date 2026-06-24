<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotinaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'slug'      => $this->slug,
            'pagina'    => $this->pagina,
            'icone'     => $this->icone,
            'parent_id' => $this->parent_id,
            'ordem'     => $this->ordem,
            'ativo'     => (bool) $this->ativo,
            'filhos'    => $this->when(
                $this->relationLoaded('filhos'),
                fn () => RotinaResource::collection($this->filhos)
            ),
        ];
    }
}
